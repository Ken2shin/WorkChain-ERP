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
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/joho/godotenv"
	"golang.org/x/crypto/argon2"
	"golang.org/x/crypto/bcrypt"
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
	Algorithm string `json:"algorithm"` // "argon2", "bcrypt", "sha256"
}

type EncryptRequest struct {
	Data string `json:"data"`
	Key  string `json:"key"` // base64 encoded (opcional)
}

type JWTRequest struct {
	UserID    int      `json:"user_id"`
	TenantID  string   `json:"tenant_id"`
	Scopes    []string `json:"scopes"`
	ExpiresIn int      `json:"expires_in"` // segundos
}

type JWTResponse struct {
	Token     string `json:"token"`
	ExpiresAt int64  `json:"expires_at"`
}

// Carga automática de variables de entorno al iniciar
func init() {
	_ = godotenv.Load()
}

// ==========================================
// FUNCIÓN PRINCIPAL (MAIN)
// ==========================================

func main() {
	// 1. Configuración de JWT
	jwtSecret := os.Getenv("JWT_SECRET")
	if jwtSecret == "" {
		jwtSecret = "default-insecure-secret-key-change-me"
		log.Println("WARNING: JWT_SECRET not set, using default")
	}

	// 2. Configuración de Master Key (AES)
	masterKeyBase64 := os.Getenv("MASTER_KEY")
	var masterKey []byte
	var err error

	if masterKeyBase64 == "" {
		masterKey = make([]byte, 32)
		rand.Read(masterKey)
		log.Println("WARNING: MASTER_KEY not set, using random temporary key")
	} else {
		masterKey, err = base64.StdEncoding.DecodeString(masterKeyBase64)
		if err != nil || len(masterKey) != 32 {
			log.Fatal("MASTER_KEY must be a valid base64 encoded 32-byte key")
		}
	}

	// 3. Inicializar Servicio
	service := &CryptoService{
		jwtSecret: []byte(jwtSecret),
		masterKey: masterKey,
	}

	// 4. Definir Rutas
	http.HandleFunc("/health", service.handleHealth)
	http.HandleFunc("/hash", service.handleHash)
	http.HandleFunc("/verify-hash", service.handleVerifyHash)
	http.HandleFunc("/encrypt", service.handleEncrypt)
	http.HandleFunc("/decrypt", service.handleDecrypt)
	http.HandleFunc("/generate-jwt", service.handleGenerateJWT)
	http.HandleFunc("/verify-jwt", service.handleVerifyJWT)

	// 5. Iniciar Servidor
	port := os.Getenv("PORT")
	if port == "" {
		port = "3000"
	}

	log.Printf("Crypto Service listening on :%s", port)
	log.Fatal(http.ListenAndServe(":"+port, nil))
}

// ==========================================
// MÉTODOS CRIPTOGRÁFICOS (HELPERS)
// ==========================================

// HashPasswordArgon2 genera un hash seguro usando Argon2id (retorna salt + hash)
func (cs *CryptoService) HashPasswordArgon2(password []byte) ([]byte, error) {
	salt := make([]byte, 16)
	if _, err := rand.Read(salt); err != nil {
		return nil, err
	}
	// Parámetros: time=3, memory=64MB, threads=4, keyLen=32
	hash := argon2.IDKey(password, salt, 3, 65536, 4, 32)
	return append(salt, hash...), nil
}

// VerifyPasswordArgon2 verifica un password contra un hash (que incluye el salt)
func (cs *CryptoService) VerifyPasswordArgon2(password []byte, storedData []byte) bool {
	if len(storedData) < 16 {
		return false
	}
	salt := storedData[:16]
	originalHash := storedData[16:]
	computedHash := argon2.IDKey(password, salt, 3, 65536, 4, 32)
	return subtle.ConstantTimeCompare(computedHash, originalHash) == 1
}

// EncryptAES256GCM cifra datos. Si 'key' es nil, usa la masterKey del servicio.
func (cs *CryptoService) EncryptAES256GCM(data []byte, key []byte) ([]byte, error) {
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
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return nil, err
	}
	return gcm.Seal(nonce, nonce, data, nil), nil
}

// DecryptAES256GCM descifra datos. Si 'key' es nil, usa la masterKey del servicio.
func (cs *CryptoService) DecryptAES256GCM(ciphertext []byte, key []byte) ([]byte, error) {
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
		return nil, fmt.Errorf("ciphertext too short")
	}
	nonce, text := ciphertext[:nonceSize], ciphertext[nonceSize:]
	return gcm.Open(nil, nonce, text, nil)
}

// HashSHA256 helper simple
func (cs *CryptoService) HashSHA256(data []byte) []byte {
	h := sha256.Sum256(data)
	return h[:]
}

// ==========================================
// HANDLERS HTTP
// ==========================================

func (cs *CryptoService) handleHealth(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "healthy"})
}

func (cs *CryptoService) handleHash(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req HashRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}
	w.Header().Set("Content-Type", "application/json")

	switch req.Algorithm {
	case "argon2":
		hashBytes, err := cs.HashPasswordArgon2([]byte(req.Data))
		if err != nil {
			http.Error(w, "Hashing failed", http.StatusInternalServerError)
			return
		}
		json.NewEncoder(w).Encode(map[string]string{
			"hash": base64.StdEncoding.EncodeToString(hashBytes),
		})
	case "bcrypt":
		hash, err := bcrypt.GenerateFromPassword([]byte(req.Data), bcrypt.DefaultCost)
		if err != nil {
			http.Error(w, "Hashing failed", http.StatusInternalServerError)
			return
		}
		json.NewEncoder(w).Encode(map[string]string{
			"hash": string(hash),
		})
	case "sha256":
		hashBytes := cs.HashSHA256([]byte(req.Data))
		json.NewEncoder(w).Encode(map[string]string{
			"hash": hex.EncodeToString(hashBytes),
		})
	default:
		http.Error(w, "Unknown algorithm", http.StatusBadRequest)
	}
}

func (cs *CryptoService) handleVerifyHash(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req struct {
		Data      string `json:"data"`
		Hash      string `json:"hash"`
		Algorithm string `json:"algorithm"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	valid := false

	switch req.Algorithm {
	case "bcrypt":
		err := bcrypt.CompareHashAndPassword([]byte(req.Hash), []byte(req.Data))
		valid = err == nil
	case "argon2":
		hashBytes, err := base64.StdEncoding.DecodeString(req.Hash)
		if err == nil {
			valid = cs.VerifyPasswordArgon2([]byte(req.Data), hashBytes)
		}
	case "sha256":
		hashBytes := cs.HashSHA256([]byte(req.Data))
		valid = (hex.EncodeToString(hashBytes) == req.Hash)
	}
	json.NewEncoder(w).Encode(map[string]bool{"valid": valid})
}

func (cs *CryptoService) handleEncrypt(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req EncryptRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}

	// Manejo de llave opcional proporcionada por el usuario
	var keyToUse []byte
	if req.Key != "" {
		var err error
		keyToUse, err = base64.StdEncoding.DecodeString(req.Key)
		if err != nil || len(keyToUse) != 32 {
			http.Error(w, "Invalid provided key (must be 32 bytes base64)", http.StatusBadRequest)
			return
		}
	} else {
		keyToUse = nil // Usará masterKey automáticamente en el método
	}

	ciphertext, err := cs.EncryptAES256GCM([]byte(req.Data), keyToUse)
	if err != nil {
		http.Error(w, "Encryption failed: "+err.Error(), http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"ciphertext": base64.StdEncoding.EncodeToString(ciphertext),
	})
}

func (cs *CryptoService) handleDecrypt(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req struct {
		Ciphertext string `json:"ciphertext"`
		Key        string `json:"key"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}

	ciphertextBytes, err := base64.StdEncoding.DecodeString(req.Ciphertext)
	if err != nil {
		http.Error(w, "Invalid ciphertext encoding", http.StatusBadRequest)
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
	} else {
		keyToUse = nil // Usará masterKey
	}

	plaintext, err := cs.DecryptAES256GCM(ciphertextBytes, keyToUse)
	if err != nil {
		http.Error(w, "Decryption failed", http.StatusBadRequest)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"plaintext": string(plaintext),
	})
}

func (cs *CryptoService) handleGenerateJWT(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req JWTRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}

	expiresAt := time.Now().Add(time.Duration(req.ExpiresIn) * time.Second).Unix()
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
		"user_id":   req.UserID,
		"tenant_id": req.TenantID,
		"scopes":    req.Scopes,
		"exp":       expiresAt,
		"iat":       time.Now().Unix(),
	})

	tokenString, err := token.SignedString(cs.jwtSecret)
	if err != nil {
		http.Error(w, "Token generation failed", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(JWTResponse{
		Token:     tokenString,
		ExpiresAt: expiresAt,
	})
}

func (cs *CryptoService) handleVerifyJWT(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req struct {
		Token string `json:"token"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}

	token, err := jwt.ParseWithClaims(req.Token, jwt.MapClaims{}, func(token *jwt.Token) (interface{}, error) {
		return cs.jwtSecret, nil
	})

	w.Header().Set("Content-Type", "application/json")
	if err != nil || !token.Valid {
		json.NewEncoder(w).Encode(map[string]interface{}{"valid": false, "error": "Invalid token"})
		return
	}
	claims := token.Claims.(jwt.MapClaims)
	json.NewEncoder(w).Encode(map[string]interface{}{"valid": true, "claims": claims})
}
