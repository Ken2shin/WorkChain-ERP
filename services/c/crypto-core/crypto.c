#include "crypto.h"
#include <string.h>
#include <stdlib.h>
#include <openssl/err.h>

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

    ctx->cipher_ctx = EVP_CIPHER_CTX_new();
    ctx->hash_ctx = EVP_MD_CTX_new();

    if (!ctx->cipher_ctx || !ctx->hash_ctx) {
        EVP_CIPHER_CTX_free(ctx->cipher_ctx);
        EVP_MD_CTX_free(ctx->hash_ctx);
        free(ctx);
        return NULL;
    }

    memcpy(ctx->key, master_key, 32);
    
    /* Generate secure random IV and salt */
    if (RAND_bytes(ctx->iv, 16) != 1 || RAND_bytes(ctx->salt, 16) != 1) {
        EVP_CIPHER_CTX_free(ctx->cipher_ctx);
        EVP_MD_CTX_free(ctx->hash_ctx);
        free(ctx);
        return NULL;
    }

    return ctx;
}

/* Free cryptographic context */
void wc_crypto_free(WorkChainCryptoContext *ctx)
{
    if (!ctx) return;

    if (ctx->cipher_ctx) {
        EVP_CIPHER_CTX_free(ctx->cipher_ctx);
    }
    if (ctx->hash_ctx) {
        EVP_MD_CTX_free(ctx->hash_ctx);
    }

    /* Secure wipe of sensitive data */
    OPENSSL_cleanse(ctx->key, 32);
    OPENSSL_cleanse(ctx->iv, 16);
    OPENSSL_cleanse(ctx->salt, 16);

    free(ctx);
}

/* Allocate secure buffer */
SecureBuffer* wc_secure_buffer_alloc(size_t size)
{
    if (size == 0 || size > (1024 * 1024 * 100)) { /* 100MB max */
        return NULL;
    }

    SecureBuffer *buf = (SecureBuffer*)malloc(sizeof(SecureBuffer));
    if (!buf) {
        return NULL;
    }

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

/* AES-256-GCM Encryption */
WCCryptoError wc_encrypt_aes256gcm(
    WorkChainCryptoContext *ctx,
    const unsigned char *plaintext,
    size_t plaintext_len,
    unsigned char *ciphertext,
    size_t *ciphertext_len,
    unsigned char *tag,
    size_t tag_len)
{
    if (!ctx || !plaintext || !ciphertext || !ciphertext_len || !tag || tag_len < 16) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    if (plaintext_len > (1024 * 1024 * 50)) { /* 50MB max */
        return WC_CRYPTO_OVERFLOW;
    }

    EVP_CIPHER_CTX *cipher_ctx = EVP_CIPHER_CTX_new();
    if (!cipher_ctx) {
        return WC_CRYPTO_MEMORY_ERROR;
    }

    int len;
    unsigned char iv[12]; /* 96-bit IV for GCM */

    if (RAND_bytes(iv, 12) != 1) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* Initialize encryption */
    if (1 != EVP_EncryptInit_ex(cipher_ctx, EVP_aes_256_gcm(), NULL, ctx->key, iv)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    /* Encrypt */
    if (1 != EVP_EncryptUpdate(cipher_ctx, ciphertext, &len, plaintext, plaintext_len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    int ciphertext_len_tmp = len;

    /* Finalize */
    if (1 != EVP_EncryptFinal_ex(cipher_ctx, ciphertext + len, &len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    ciphertext_len_tmp += len;

    /* Get authentication tag */
    if (1 != EVP_CIPHER_CTX_ctrl(cipher_ctx, EVP_CTRL_GCM_GET_TAG, tag_len, tag)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    *ciphertext_len = ciphertext_len_tmp;

    /* Prepend IV to ciphertext */
    memmove(ciphertext + 12, ciphertext, ciphertext_len_tmp);
    memcpy(ciphertext, iv, 12);
    *ciphertext_len += 12;

    EVP_CIPHER_CTX_free(cipher_ctx);

    return WC_CRYPTO_SUCCESS;
}

/* AES-256-GCM Decryption */
WCCryptoError wc_decrypt_aes256gcm(
    WorkChainCryptoContext *ctx,
    const unsigned char *ciphertext,
    size_t ciphertext_len,
    unsigned char *plaintext,
    size_t *plaintext_len,
    const unsigned char *tag,
    size_t tag_len)
{
    if (!ctx || !ciphertext || !plaintext || !plaintext_len || !tag || tag_len < 16 || ciphertext_len < 12) {
        return WC_CRYPTO_INVALID_INPUT;
    }

    EVP_CIPHER_CTX *cipher_ctx = EVP_CIPHER_CTX_new();
    if (!cipher_ctx) {
        return WC_CRYPTO_MEMORY_ERROR;
    }

    const unsigned char *iv = ciphertext;
    const unsigned char *actual_ciphertext = ciphertext + 12;
    size_t actual_ciphertext_len = ciphertext_len - 12;

    int len;

    if (1 != EVP_DecryptInit_ex(cipher_ctx, EVP_aes_256_gcm(), NULL, ctx->key, iv)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    if (1 != EVP_DecryptUpdate(cipher_ctx, plaintext, &len, actual_ciphertext, actual_ciphertext_len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    int plaintext_len_tmp = len;

    if (1 != EVP_CIPHER_CTX_ctrl(cipher_ctx, EVP_CTRL_GCM_SET_TAG, (int)tag_len, (unsigned char*)tag)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
    }

    if (1 != EVP_DecryptFinal_ex(cipher_ctx, plaintext + len, &len)) {
        EVP_CIPHER_CTX_free(cipher_ctx);
        return WC_CRYPTO_FAILURE;
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

    unsigned char digest[SHA256_DIGEST_LENGTH];
    unsigned int digest_len;

    if (SHA256(data, data_len, digest) == NULL) {
        return WC_CRYPTO_FAILURE;
    }

    memcpy(hash, digest, SHA256_DIGEST_LENGTH);
    *hash_len = SHA256_DIGEST_LENGTH;

    OPENSSL_cleanse(digest, SHA256_DIGEST_LENGTH);

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

    unsigned char digest[SHA512_DIGEST_LENGTH];
    unsigned int digest_len;

    if (SHA512(data, data_len, digest) == NULL) {
        return WC_CRYPTO_FAILURE;
    }

    memcpy(hash, digest, SHA512_DIGEST_LENGTH);
    *hash_len = SHA512_DIGEST_LENGTH;

    OPENSSL_cleanse(digest, SHA512_DIGEST_LENGTH);

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
    if (!password || !salt || !derived_key || iterations < 100000 || derived_key_len > 64) {
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
    if (!a || !b) {
        return -1;
    }

    return CRYPTO_memcmp(a, b, len);
}
