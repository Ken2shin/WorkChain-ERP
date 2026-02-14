package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/joho/godotenv"
	"golang.org/x/crypto/argon2"
)

// ==========================================
// ESTRUCTURAS Y CONFIGURACIÓN
// ==========================================

// CryptoService centraliza las llaves y la lógica
type CryptoService struct {
	jwtSecret []byte
	masterKey []byte
}

// Estructuras para peticiones JSON
type HashRequest struct {
	Data      string `json:"data"`
	Algorithm string `json:"algorithm"` // "argon2", "sha256"
}

type EncryptRequest struct {
	Data      string `json:"data"`
	Key       string `json:"key"`        // base64 (opcional)
	ContextID string `json:"context_id"` // OBLIGATORIO: TenantID / OrgID (AAD)
}

type DecryptRequest struct {
	Ciphertext string `json:"ciphertext"`
	Key        string `json:"key"`        // base64 (opcional)
	ContextID  string `json:"context_id"` // OBLIGATORIO: Debe coincidir con el de encriptación
}

type JWTRequest struct {
	UserID    string   `json:"user_id"`
	TenantID  string   `json:"tenant_id"` // Obligatorio
	Scopes    []string `json:"scopes"`
	ExpiresIn int      `json:"expires_in"` // segundos
}

type JWTResponse struct {
	Token     string `json:"token"`
	ExpiresAt int64  `json:"expires_at"`
}

// Carga automática de variables de entorno
func init() {
	_ = godotenv.Load()
}

// ==========================================
// FUNCIÓN PRINCIPAL (MAIN)
// ==========================================

func main() {
	// 1. Configuración de Seguridad (FAIL-SECURE)
	// Si no hay secretos, la aplicación DEBE fallar, nunca usar defaults inseguros.
	jwtSecretStr := os.Getenv("JWT_SECRET")
	if len(jwtSecretStr) < 32 {
		log.Fatal("CRITICAL: JWT_SECRET must be set and at least 32 chars long.")
	}

	masterKeyBase64 := os.Getenv("MASTER_KEY")
	if masterKeyBase64 == "" {
		log.Fatal("CRITICAL: MASTER_KEY is not set.")
	}

	masterKey, err := base64.StdEncoding.DecodeString(masterKeyBase64)
	if err != nil || len(masterKey) != 32 {
		log.Fatal("CRITICAL: MASTER_KEY must be a valid base64 encoded 32-byte key (AES-256).")
	}

	// 2. Inicializar Servicio
	service := &CryptoService{
		jwtSecret: []byte(jwtSecretStr),
		masterKey: masterKey,
	}

	// 3. Router & Middleware
	mux := http.NewServeMux()
	mux.HandleFunc("POST /hash", service.handleHash)
	mux.HandleFunc("POST /verify-hash", service.handleVerifyHash)
	mux.HandleFunc("POST /encrypt", service.handleEncrypt) // Ahora soporta AAD
	mux.HandleFunc("POST /decrypt", service.handleDecrypt) // Ahora valida AAD
	mux.HandleFunc("POST /generate-jwt", service.handleGenerateJWT)
	mux.HandleFunc("POST /verify-jwt", service.handleVerifyJWT)
	mux.HandleFunc("GET /health", service.handleHealth)

	// 4. Iniciar Servidor
	port := os.Getenv("PORT")
	if port == "" {
		port = "3000"
	}

	server := &http.Server{
		Addr:         ":" + port,
		Handler:      limitBodySize(mux), // Protección básica contra DoS
		ReadTimeout:  5 * time.Second,
		WriteTimeout: 10 * time.Second,
	}

	log.Printf("🔒 Secure Crypto Service listening on :%s", port)
	log.Fatal(server.ListenAndServe())
}

// Middleware simple para limitar tamaño del body (Evitar Buffer Overflow / DoS)
func limitBodySize(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		r.Body = http.MaxBytesReader(w, r.Body, 1024*1024) // 1MB Max
		next.ServeHTTP(w, r)
	})
}

// ==========================================
// MÉTODOS CRIPTOGRÁFICOS (CORE)
// ==========================================

// HashPasswordArgon2id estándar OWASP
func (cs *CryptoService) HashPasswordArgon2(password []byte) ([]byte, error) {
	salt := make([]byte, 16)
	if _, err := rand.Read(salt); err != nil {
		return nil, err
	}
	// Argon2id: Time=1, Memory=64MB, Threads=4, KeyLen=32
	hash := argon2.IDKey(password, salt, 1, 64*1024, 4, 32)

	// Retornamos formato: salt (16 bytes) + hash (32 bytes)
	combined := append(salt, hash...)
	return combined, nil
}

// VerifyPasswordArgon2 verifica hash
func (cs *CryptoService) VerifyPasswordArgon2(password []byte, storedData []byte) bool {
	if len(storedData) < 16+32 {
		return false
	}
	salt := storedData[:16]
	originalHash := storedData[16:]

	computedHash := argon2.IDKey(password, salt, 1, 64*1024, 4, 32)

	// Comparación en tiempo constante para evitar Timing Attacks
	return subtle.ConstantTimeCompare(computedHash, originalHash) == 1
}

// EncryptAES256GCM_AAD cifra usando AES-GCM con Contexto (AAD)
// AAD (Additional Authenticated Data) vincula el cifrado al TenantID/ContextID
func (cs *CryptoService) EncryptAES256GCM(data []byte, key []byte, aad []byte) ([]byte, error) {
	keyToUse := key
	if len(keyToUse) == 0 {
		keyToUse = cs.masterKey
	}

	block, err := aes.NewCipher(keyToUse)
	if err != nil {
		return nil, err
	}

	// GCM Mode
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}

	// Nonce Aleatorio (Nunca reutilizar nonces con la misma llave)
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return nil, err
	}

	// Seal: data + aad. El AAD no se cifra, pero se autentica.
	// El formato final es: nonce + ciphertext + tag (el tag lo añade Seal automáticamente)
	ciphertext := gcm.Seal(nonce, nonce, data, aad)
	return ciphertext, nil
}

// DecryptAES256GCM_AAD descifra validando el Contexto (AAD)
func (cs *CryptoService) DecryptAES256GCM(ciphertext []byte, key []byte, aad []byte) ([]byte, error) {
	keyToUse := key
	if len(keyToUse) == 0 {
		keyToUse = cs.masterKey
	}

	block, err := aes.NewCipher(keyToUse)
	if err != nil {
		return nil, err
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}

	nonceSize := gcm.NonceSize()
	if len(ciphertext) < nonceSize {
		return nil, errors.New("ciphertext malformed")
	}

	nonce, actualCiphertext := ciphertext[:nonceSize], ciphertext[nonceSize:]

	// Open verifica el AAD. Si el TenantID (aad) no coincide con el usado al cifrar,
	// Open devuelve error "message authentication failed".
	plaintext, err := gcm.Open(nil, nonce, actualCiphertext, aad)
	if err != nil {
		// Mensaje genérico para evitar Padding Oracle attacks (aunque GCM es robusto)
		return nil, errors.New("decryption failed or invalid context")
	}

	return plaintext, nil
}

func (cs *CryptoService) HashSHA256(data []byte) []byte {
	h := sha256.Sum256(data)
	return h[:]
}

// ==========================================
// HANDLERS HTTP
// ==========================================

func (cs *CryptoService) handleHealth(w http.ResponseWriter, r *http.Request) {
	jsonResponse(w, map[string]string{"status": "healthy", "mode": "secure"})
}

func (cs *CryptoService) handleHash(w http.ResponseWriter, r *http.Request) {
	var req HashRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	var result string
	switch req.Algorithm {
	case "argon2":
		hashBytes, err := cs.HashPasswordArgon2([]byte(req.Data))
		if err != nil {
			http.Error(w, "Hashing failed", http.StatusInternalServerError)
			return
		}
		result = base64.StdEncoding.EncodeToString(hashBytes)
	case "sha256":
		hashBytes := cs.HashSHA256([]byte(req.Data))
		result = hex.EncodeToString(hashBytes)
	default:
		http.Error(w, "Algorithm not supported (use 'argon2' or 'sha256')", http.StatusBadRequest)
		return
	}

	jsonResponse(w, map[string]string{"hash": result})
}

func (cs *CryptoService) handleVerifyHash(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Data      string `json:"data"`
		Hash      string `json:"hash"`
		Algorithm string `json:"algorithm"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	valid := false
	switch req.Algorithm {
	case "argon2":
		hashBytes, err := base64.StdEncoding.DecodeString(req.Hash)
		if err == nil {
			valid = cs.VerifyPasswordArgon2([]byte(req.Data), hashBytes)
		}
	case "sha256":
		hashBytes := cs.HashSHA256([]byte(req.Data))
		valid = (hex.EncodeToString(hashBytes) == req.Hash)
	}

	jsonResponse(w, map[string]bool{"valid": valid})
}

// handleEncrypt ahora EXIGE un ContextID (TenantID)
func (cs *CryptoService) handleEncrypt(w http.ResponseWriter, r *http.Request) {
	var req EncryptRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	if req.ContextID == "" {
		http.Error(w, "Security Error: 'context_id' (TenantID) is mandatory for encryption.", http.StatusBadRequest)
		return
	}

	var keyToUse []byte
	if req.Key != "" {
		var err error
		keyToUse, err = base64.StdEncoding.DecodeString(req.Key)
		if err != nil || len(keyToUse) != 32 {
			http.Error(w, "Invalid provided key", http.StatusBadRequest)
			return
		}
	}

	// Pasamos ContextID como AAD
	ciphertext, err := cs.EncryptAES256GCM([]byte(req.Data), keyToUse, []byte(req.ContextID))
	if err != nil {
		http.Error(w, "Encryption error", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, map[string]string{
		"ciphertext": base64.StdEncoding.EncodeToString(ciphertext),
	})
}

// handleDecrypt valida que el ContextID coincida
func (cs *CryptoService) handleDecrypt(w http.ResponseWriter, r *http.Request) {
	var req DecryptRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	if req.ContextID == "" {
		http.Error(w, "Security Error: 'context_id' (TenantID) is mandatory for decryption.", http.StatusBadRequest)
		return
	}

	ciphertextBytes, err := base64.StdEncoding.DecodeString(req.Ciphertext)
	if err != nil {
		http.Error(w, "Invalid ciphertext format", http.StatusBadRequest)
		return
	}

	var keyToUse []byte
	if req.Key != "" {
		var err error
		keyToUse, err = base64.StdEncoding.DecodeString(req.Key)
		if err != nil || len(keyToUse) != 32 {
			http.Error(w, "Invalid provided key", http.StatusBadRequest)
			return
		}
	}

	// Pasamos ContextID como AAD. Si no coincide con el cifrado, falla.
	plaintext, err := cs.DecryptAES256GCM(ciphertextBytes, keyToUse, []byte(req.ContextID))
	if err != nil {
		// Retornamos 403 Forbidden porque implica que el contexto es inválido o la data está corrupta
		http.Error(w, "Decryption failed: Invalid authentication context or corrupted data", http.StatusForbidden)
		return
	}

	jsonResponse(w, map[string]string{
		"plaintext": string(plaintext),
	})
}

func (cs *CryptoService) handleGenerateJWT(w http.ResponseWriter, r *http.Request) {
	var req JWTRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	if req.TenantID == "" {
		http.Error(w, "TenantID is required for JWT generation", http.StatusBadRequest)
		return
	}

	if req.ExpiresIn <= 0 {
		req.ExpiresIn = 3600 // Default 1 hora
	}

	now := time.Now()
	expiresAt := now.Add(time.Duration(req.ExpiresIn) * time.Second)

	claims := jwt.MapClaims{
		"sub":       req.UserID,
		"tenant_id": req.TenantID, // Identificador de Organización
		"scopes":    req.Scopes,
		"exp":       expiresAt.Unix(),
		"iat":       now.Unix(),
		"nbf":       now.Unix(), // Not Before
		"iss":       "workchain-crypto-service",
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	tokenString, err := token.SignedString(cs.jwtSecret)
	if err != nil {
		http.Error(w, "Token generation failed", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, JWTResponse{
		Token:     tokenString,
		ExpiresAt: expiresAt.Unix(),
	})
}

func (cs *CryptoService) handleVerifyJWT(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Token string `json:"token"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	token, err := jwt.Parse(req.Token, func(token *jwt.Token) (interface{}, error) {
		if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
			return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
		}
		return cs.jwtSecret, nil
	})

	if err != nil || !token.Valid {
		jsonResponse(w, map[string]interface{}{"valid": false, "error": "Invalid or expired token"})
		return
	}

	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		jsonResponse(w, map[string]interface{}{"valid": false, "error": "Invalid claims format"})
		return
	}

	jsonResponse(w, map[string]interface{}{"valid": true, "claims": claims})
}

// Helper para respuestas JSON consistentes
func jsonResponse(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(w).Encode(data); err != nil {
		log.Printf("Error encoding response: %v", err)
	}
}
