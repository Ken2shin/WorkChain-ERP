#include "crypto.h"
#include <string.h>
#include <stdlib.h>
#include <openssl/err.h>
#include <openssl/rand.h>
#include <openssl/evp.h>
#include <openssl/hmac.h>

/* * CRITICAL CONFIGURATION:
 * Ensure your header file defines WCCryptoError constants properly.
 * Example: WC_CRYPTO_AUTH_FAILED = -2
 */

/* Initialize cryptographic context */
WorkChainCryptoContext* wc_crypto_init(const unsigned char *master_key, size_t key_len)
{
    if (!master_key || key_len != 32) {
        return NULL;
    }

    WorkChainCryptoContext *ctx = (WorkChainCryptoContext*)malloc(sizeof(WorkChainCryptoContext));
    if (!ctx) {
        return NULL;
    }

    /* Note: We don't pre-allocate EVP_CIPHER_CTX here to ensure thread safety 
       if the context is shared. We allocate them on demand. */
    ctx->cipher_ctx = NULL; 
    ctx->hash_ctx = NULL;

    memcpy(ctx->key, master_key, 32);
    
    /* Generate secure random salt for key derivation if needed later */
    if (RAND_bytes(ctx->salt, 16) != 1) {
        free(ctx);
        return NULL;
    }
    
    /* Zero out the structural IV, we will generate unique IVs per message */
    memset(ctx->iv, 0, 16);

    return ctx;
}

/* Free cryptographic context */
void wc_crypto_free(WorkChainCryptoContext *ctx)
{
    if (!ctx) return;

    /* Secure wipe of sensitive data */
    OPENSSL_cleanse(ctx->key, 32);
    OPENSSL_cleanse(ctx->iv, 16);
    OPENSSL_cleanse(ctx->salt, 16);

    free(ctx);
}

/* Allocate secure buffer */
SecureBuffer* wc_secure_buffer_alloc(size_t size)
{
    if (size == 0 || size > (1024 * 1024 * 100)) { /* 100MB max sanity check */
        return NULL;
    }

    SecureBuffer *buf = (SecureBuffer*)malloc(sizeof(SecureBuffer));
    if (!buf) return NULL;

    buf->data = (unsigned char*)OPENSSL_malloc(size);
    if (!buf->data) {
        free(buf);
        return NULL;
    }

    buf->size = 0;
    buf->allocated = size;

    return buf;
}

/* Free secure buffer */
void wc_secure_buffer_free(SecureBuffer *buf)
{
    if (!buf) return;

    if (buf->data) {
        OPENSSL_cleanse(buf->data, buf->allocated);
        OPENSSL_free(buf->data);
    }

    free(buf);
}

/* Secure wipe buffer */
WCCryptoError wc_secure_buffer_wipe(SecureBuffer *buf)
{
    if (!buf || !buf->data) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    OPENSSL_cleanse(buf->data, buf->allocated);
    buf->size = 0;

    return WC_CRYPTO_SUCCESS;
}

/* * AES-256-GCM Encryption WITH ORGANIZATION BINDING (AAD)
 * * aad: Additional Authenticated Data (Pass the Organization UUID here!)
 * aad_len: Length of the Org ID
 */
WCCryptoError wc_encrypt_aes256gcm(
    WorkChainCryptoContext *ctx,
    const unsigned char *plaintext,
    size_t plaintext_len,
    const unsigned char *aad,      /* <--- NEW: Context Binding (OrgID) */
    size_t aad_len,                /* <--- NEW */
    unsigned char *ciphertext,
    size_t *ciphertext_len,
    unsigned char *tag,
    size_t tag_len)
{
    if (!ctx || !plaintext || !ciphertext || !ciphertext_len || !tag || tag_len < 16) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    if (plaintext_len > (1024 * 1024 * 50)) { 
        return WC_CRYPTO_OVERFLOW;
    }

    EVP_CIPHER_CTX *cipher_ctx = EVP_CIPHER_CTX_new();
    if (!cipher_ctx) return WC_CRYPTO_MEMORY_ERROR;

    int len;
    unsigned char iv[12]; /* 96-bit IV standard for GCM */

    /* Generate a FRESH IV for every encryption. Critical for GCM Security. */
    if (RAND_bytes(iv, 12) != 1) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* Initialize encryption */
    if (1 != EVP_EncryptInit_ex(cipher_ctx, EVP_aes_256_gcm(), NULL, ctx->key, iv)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* * CRITICAL FIX: Add AAD (Organization ID binding).
     * This ensures the ciphertext is mathematically invalid if used in the wrong Org context.
     */
    if (aad && aad_len > 0) {
        if (1 != EVP_EncryptUpdate(cipher_ctx, NULL, &len, aad, aad_len)) {
            EVP_CIPHER_CTX_free(cipher_ctx);
            return WC_CRYPTO_FAILURE;
        }
    }

    /* Encrypt body */
    if (1 != EVP_EncryptUpdate(cipher_ctx, ciphertext + 12, &len, plaintext, plaintext_len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    int ciphertext_body_len = len;

    /* Finalize */
    if (1 != EVP_EncryptFinal_ex(cipher_ctx, ciphertext + 12 + len, &len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }
    ciphertext_body_len += len;

    /* Get authentication tag */
    if (1 != EVP_CIPHER_CTX_ctrl(cipher_ctx, EVP_CTRL_GCM_GET_TAG, tag_len, tag)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* Prepend IV to ciphertext (IV || Ciphertext) */
    /* Note: We encrypt directly to offset +12, so we just copy IV to 0-11 */
    memcpy(ciphertext, iv, 12);
    
    *ciphertext_len = ciphertext_body_len + 12;

    EVP_CIPHER_CTX_free(cipher_ctx);

    return WC_CRYPTO_SUCCESS;
}

/* * AES-256-GCM Decryption WITH ORGANIZATION VALIDATION
 */
WCCryptoError wc_decrypt_aes256gcm(
    WorkChainCryptoContext *ctx,
    const unsigned char *ciphertext,
    size_t ciphertext_len,
    const unsigned char *aad,      /* <--- NEW: Must match Encrypt AAD */
    size_t aad_len,                /* <--- NEW */
    unsigned char *plaintext,
    size_t *plaintext_len,
    const unsigned char *tag,
    size_t tag_len)
{
    if (!ctx || !ciphertext || !plaintext || !plaintext_len || !tag || tag_len < 16 || ciphertext_len < 12) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    EVP_CIPHER_CTX *cipher_ctx = EVP_CIPHER_CTX_new();
    if (!cipher_ctx) return WC_CRYPTO_MEMORY_ERROR;

    /* Extract IV from the first 12 bytes */
    const unsigned char *iv = ciphertext;
    const unsigned char *actual_ciphertext = ciphertext + 12;
    size_t actual_ciphertext_len = ciphertext_len - 12;

    int len;

    if (1 != EVP_DecryptInit_ex(cipher_ctx, EVP_aes_256_gcm(), NULL, ctx->key, iv)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* * CRITICAL FIX: Feed AAD (Organization ID) to the decryptor.
     * If this doesn't match what was used during encryption, DecryptFinal will fail.
     */
    if (aad && aad_len > 0) {
        if (1 != EVP_DecryptUpdate(cipher_ctx, NULL, &len, aad, aad_len)) {
            EVP_CIPHER_CTX_free(cipher_ctx);
            return WC_CRYPTO_FAILURE;
        }
    }

    /* Decrypt body */
    if (1 != EVP_DecryptUpdate(cipher_ctx, plaintext, &len, actual_ciphertext, actual_ciphertext_len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    int plaintext_len_tmp = len;

    /* Set expected tag */
    if (1 != EVP_CIPHER_CTX_ctrl(cipher_ctx, EVP_CTRL_GCM_SET_TAG, (int)tag_len, (void*)tag)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* * Finalize (Check Tag + AAD) 
     * If this returns 0 or < 0, it means AUTHENTICATION FAILED.
     * The Org ID was wrong or data was tampered.
     */
    if (EVP_DecryptFinal_ex(cipher_ctx, plaintext + len, &len) <= 0) {
        /* WIPE plaintext buffer to be safe */
        OPENSSL_cleanse(plaintext, plaintext_len_tmp); 
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_AUTH_FAILED; /* Define this in your header */
    }

    plaintext_len_tmp += len;
    *plaintext_len = plaintext_len_tmp;

    EVP_CIPHER_CTX_free(cipher_ctx);

    return WC_CRYPTO_SUCCESS;
}

/* SHA-256 Hashing */
WCCryptoError wc_hash_sha256(
    const unsigned char *data,
    size_t data_len,
    unsigned char *hash,
    size_t *hash_len)
{
    if (!data || !hash || !hash_len || *hash_len < SHA256_DIGEST_LENGTH) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    if (SHA256(data, data_len, hash) == NULL) {
        return WC_CRYPTO_FAILURE;
    }
    *hash_len = SHA256_DIGEST_LENGTH;

    return WC_CRYPTO_SUCCESS;
}

/* SHA-512 Hashing */
WCCryptoError wc_hash_sha512(
    const unsigned char *data,
    size_t data_len,
    unsigned char *hash,
    size_t *hash_len)
{
    if (!data || !hash || !hash_len || *hash_len < SHA512_DIGEST_LENGTH) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    if (SHA512(data, data_len, hash) == NULL) {
        return WC_CRYPTO_FAILURE;
    }
    *hash_len = SHA512_DIGEST_LENGTH;

    return WC_CRYPTO_SUCCESS;
}

/* HMAC-SHA256 */
WCCryptoError wc_hmac_sha256(
    const unsigned char *key,
    size_t key_len,
    const unsigned char *data,
    size_t data_len,
    unsigned char *hmac,
    size_t *hmac_len)
{
    if (!key || !data || !hmac || !hmac_len || *hmac_len < EVP_MAX_MD_SIZE) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    unsigned int hmac_len_int;

    if (HMAC(EVP_sha256(), key, key_len, data, data_len, hmac, &hmac_len_int) == NULL) {
        return WC_CRYPTO_FAILURE;
    }

    *hmac_len = (size_t)hmac_len_int;

    return WC_CRYPTO_SUCCESS;
}

/* PBKDF2 Key Derivation */
WCCryptoError wc_derive_key_pbkdf2(
    const unsigned char *password,
    size_t password_len,
    const unsigned char *salt,
    size_t salt_len,
    int iterations,
    unsigned char *derived_key,
    size_t derived_key_len)
{
    if (!password || !salt || !derived_key || iterations < 10000) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    if (PKCS5_PBKDF2_HMAC((const char*)password, password_len, salt, salt_len,
                           iterations, EVP_sha512(), derived_key_len, derived_key) != 1) {
        return WC_CRYPTO_FAILURE;
    }

    return WC_CRYPTO_SUCCESS;
}

/* Random Bytes */
WCCryptoError wc_random_bytes(unsigned char *buf, size_t buf_len)
{
    if (!buf || buf_len == 0 || buf_len > (1024 * 1024)) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    if (RAND_bytes(buf, buf_len) != 1) {
        return WC_CRYPTO_FAILURE;
    }

    return WC_CRYPTO_SUCCESS;
}

/* Constant-time comparison */
int wc_constant_time_compare(const unsigned char *a, const unsigned char *b, size_t len)
{
    if (!a || !b) return -1;
    return CRYPTO_memcmp(a, b, len);
}