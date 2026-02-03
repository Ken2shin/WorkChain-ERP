<?php

return [
    /**
     * WAF Configuration
     */
    'waf' => [
        'enabled' => env('WAF_ENABLED', true),
        'block_on_detection' => env('WAF_BLOCK_ON_DETECTION', true),
        'log_violations' => env('WAF_LOG_VIOLATIONS', true),
    ],

    /**
     * Rate Limiting Configuration
     */
    'rate_limiting' => [
        'enabled' => env('RATE_LIMITING_ENABLED', true),
        
        // Base limits (requests per minute)
        'limits' => [
            'unauthenticated' => env('RATE_LIMIT_UNAUTHENTICATED', 60),
            'authenticated' => env('RATE_LIMIT_AUTHENTICATED', 300),
            'admin' => env('RATE_LIMIT_ADMIN', 1000),
        ],

        // Backoff configuration
        'backoff_multiplier' => env('RATE_LIMITING_BACKOFF_MULTIPLIER', 0.5),
        'max_backoff_level' => env('RATE_LIMITING_MAX_BACKOFF_LEVEL', 10),
        
        // Reset time (seconds)
        'reset_interval' => env('RATE_LIMITING_RESET_INTERVAL', 3600),
    ],

    /**
     * Anomaly Detection Configuration
     */
    'anomaly_detection' => [
        'enabled' => env('ANOMALY_DETECTION_ENABLED', true),
        'service_url' => env('ANOMALY_SERVICE_URL', 'http://localhost:3001'),
        
        // Scoring thresholds
        'thresholds' => [
            'low' => 2.0,
            'medium' => 4.0,
            'high' => 7.0,
        ],

        // Auto-response levels
        'auto_block_threshold' => env('ANOMALY_AUTO_BLOCK_THRESHOLD', 9.0),
        'auto_throttle_threshold' => env('ANOMALY_AUTO_THROTTLE_THRESHOLD', 7.0),
    ],

    /**
     * JWT Configuration
     */
    'jwt' => [
        'secret' => env('JWT_SECRET', 'change-me-in-production'),
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        'ttl' => env('JWT_TTL', 3600), // seconds
        'refresh_ttl' => env('JWT_REFRESH_TTL', 604800), // 7 days
        'issuer' => env('JWT_ISSUER', 'workchain-erp'),
    ],

    /**
     * Cryptography Service Configuration
     */
    'crypto' => [
        'service_url' => env('CRYPTO_SERVICE_URL', 'http://localhost:3000'),
        
        // Hash algorithms
        'password_algorithm' => env('PASSWORD_ALGORITHM', 'argon2'),
        'hash_algorithms' => ['argon2', 'bcrypt', 'sha256'],
        
        // Encryption
        'default_cipher' => env('CIPHER', 'AES-256-GCM'),
    ],

    /**
     * Request Size Limits
     */
    'request' => [
        'max_body_size' => env('MAX_BODY_SIZE', 10 * 1024 * 1024), // 10MB
        'max_json_size' => env('MAX_JSON_SIZE', 5 * 1024 * 1024), // 5MB
        'max_form_fields' => env('MAX_FORM_FIELDS', 500),
    ],

    /**
     * Allowed Hosts (CSRF/Host header validation)
     */
    'allowed_hosts' => explode(',', env('ALLOWED_HOSTS', 'localhost,127.0.0.1')),

    /**
     * CORS Configuration
     */
    'cors' => [
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3002')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Tenant-ID', 'X-Requested-With'],
        'expose_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
        'max_age' => 3600,
        'supports_credentials' => true,
    ],

    /**
     * Security Headers
     */
    'headers' => [
        'strict_transport_security' => env('STS_MAX_AGE', 31536000),
        'x_frame_options' => env('X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => env('X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'x_xss_protection' => env('X_XSS_PROTECTION', '1; mode=block'),
        'content_security_policy' => env('CSP_HEADER', "default-src 'self'"),
        'referrer_policy' => env('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
    ],

    /**
     * Audit Logging
     */
    'audit' => [
        'enabled' => env('AUDIT_LOGGING_ENABLED', true),
        'log_channel' => env('AUDIT_LOG_CHANNEL', 'security'),
        
        // What to log
        'log_auth_attempts' => true,
        'log_data_access' => true,
        'log_data_changes' => true,
        'log_admin_actions' => true,
        'log_security_events' => true,
        
        // Retention (days)
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),
    ],

    /**
     * Multi-Tenant Configuration
     */
    'multi_tenant' => [
        'enforce' => env('MULTI_TENANT_ENFORCE', true),
        'header' => env('TENANT_HEADER', 'X-Tenant-ID'),
        'cookie' => env('TENANT_COOKIE', 'tenant_id'),
    ],

    /**
     * IP Filtering & Geolocation
     */
    'ip_filtering' => [
        'enabled' => env('IP_FILTERING_ENABLED', false),
        'whitelist' => explode(',', env('IP_WHITELIST', '')),
        'blacklist' => explode(',', env('IP_BLACKLIST', '')),
        'geolocation_service' => env('GEOLOCATION_SERVICE', 'maxmind'),
    ],

    /**
     * Session Configuration
     */
    'session' => [
        'timeout' => env('SESSION_TIMEOUT', 1800), // 30 minutes
        'inactivity_timeout' => env('INACTIVITY_TIMEOUT', 900), // 15 minutes
        'secure_cookie' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'strict'),
    ],

    /**
     * Encryption at Rest
     */
    'encryption' => [
        'at_rest' => env('ENCRYPTION_AT_REST', false),
        'sensitive_fields' => [
            'users.email',
            'users.phone',
            'users.document_id',
        ],
    ],

    /**
     * Behavioral Analysis
     */
    'behavioral' => [
        'enabled' => env('BEHAVIORAL_ANALYSIS_ENABLED', true),
        'learn_window' => env('BEHAVIORAL_LEARN_WINDOW', 604800), // 7 days
        'sensitivity' => env('BEHAVIORAL_SENSITIVITY', 'medium'), // low, medium, high
    ],

    /**
     * API Security
     */
    'api' => [
        'versioning_enabled' => true,
        'deprecated_versions' => explode(',', env('DEPRECATED_API_VERSIONS', 'v1')),
        'key_header' => 'X-API-Key',
        'rate_limit_header' => 'X-RateLimit-Limit',
    ],
];
