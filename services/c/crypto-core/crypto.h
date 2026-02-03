#ifndef WORKCHAIN_CRYPTO_H
#define WORKCHAIN_CRYPTO_H

#include <stdint.h>
#include <stddef.h>
#include <openssl/evp.h>
#include <openssl/rand.h>
#include <openssl/sha.h>
#include <openssl/aes.h>

/* Context for cryptographic operations */
typedef struct {
    EVP_CIPHER_CTX *cipher_ctx;
    EVP_MD_CTX *hash_ctx;
    unsigned char key[32];      /* 256-bit key */
    unsigned char iv[16];       /* 128-bit IV */
    unsigned char salt[16];     /* 128-bit salt */
} WorkChainCryptoContext;

/* Secure memory structure */
typedef struct {
    unsigned char *data;
    size_t size;
    size_t allocated;
} SecureBuffer;

/* Return codes */
typedef enum {
    WC_CRYPTO_SUCCESS = 0,
    WC_CRYPTO_FAILURE = 1,
    WC_CRYPTO_INVALID_INPUT = 2,
    WC_CRYPTO_MEMORY_ERROR = 3,
    WC_CRYPTO_OVERFLOW = 4
} WCCryptoError;

/* Core API */
WorkChainCryptoContext* wc_crypto_init(const unsigned char *master_key, size_t key_len);
void wc_crypto_free(WorkChainCryptoContext *ctx);

/* Secure memory operations */
SecureBuffer* wc_secure_buffer_alloc(size_t size);
void wc_secure_buffer_free(SecureBuffer *buf);
WCCryptoError wc_secure_buffer_wipe(SecureBuffer *buf);

/* Encryption/Decryption (AES-256-GCM) */
WCCryptoError wc_encrypt_aes256gcm(
    WorkChainCryptoContext *ctx,
    const unsigned char *plaintext,
    size_t plaintext_len,
    unsigned char *ciphertext,
    size_t *ciphertext_len,
    unsigned char *tag,
    size_t tag_len
);

WCCryptoError wc_decrypt_aes256gcm(
    WorkChainCryptoContext *ctx,
    const unsigned char *ciphertext,
    size_t ciphertext_len,
    unsigned char *plaintext,
    size_t *plaintext_len,
    const unsigned char *tag,
    size_t tag_len
);

/* Hashing (SHA-256, SHA-512) */
WCCryptoError wc_hash_sha256(
    const unsigned char *data,
    size_t data_len,
    unsigned char *hash,
    size_t *hash_len
);

WCCryptoError wc_hash_sha512(
    const unsigned char *data,
    size_t data_len,
    unsigned char *hash,
    size_t *hash_len
);

/* HMAC operations */
WCCryptoError wc_hmac_sha256(
    const unsigned char *key,
    size_t key_len,
    const unsigned char *data,
    size_t data_len,
    unsigned char *hmac,
    size_t *hmac_len
);

/* Key derivation (PBKDF2) */
WCCryptoError wc_derive_key_pbkdf2(
    const unsigned char *password,
    size_t password_len,
    const unsigned char *salt,
    size_t salt_len,
    int iterations,
    unsigned char *derived_key,
    size_t derived_key_len
);

/* Random number generation */
WCCryptoError wc_random_bytes(unsigned char *buf, size_t buf_len);

/* Constant-time comparison */
int wc_constant_time_compare(const unsigned char *a, const unsigned char *b, size_t len);

#endif /* WORKCHAIN_CRYPTO_H */
