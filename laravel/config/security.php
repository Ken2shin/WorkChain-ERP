<?php

return [
    /**
     * 🛡️ WAF (Web Application Firewall) Configuration
     */
    'waf' => [
        'enabled' => env('WAF_ENABLED', true),
        'block_on_detection' => env('WAF_BLOCK_ON_DETECTION', true),
        'log_violations' => env('WAF_LOG_VIOLATIONS', true),
        'patterns' => [
            'sqli' => true,
            'xss'  => true,
            'lfi'  => true,
        ],
    ],

    /**
     * 🏢 Multi-Tenant Configuration
     * CRÍTICO PARA EL LOGIN EN RENDER:
     * El 'central_domain' debe coincidir exactamente con tu URL de Render para que
     * el sistema sepa distinguir entre el panel global y las organizaciones.
     */
    'multi_tenant' => [
        'enabled' => env('MULTI_TENANT_ENABLED', true),
        
        /**
         * Estrategia:
         * Si usas el dominio gratuito de Render (onrender.com), los subdominios
         * (cliente.workchain-erp.onrender.com) A VECES requieren configuración DNS extra.
         * Si tienes problemas, cambia esto a 'path' o usa un dominio personalizado.
         */
        'identification_strategy' => env('TENANT_ID_STRATEGY', 'subdomain'),

        // CORREGIDO: Usar tu dominio real de producción como default
        'central_domain' => env('CENTRAL_DOMAIN', 'workchain-erp.onrender.com'),

        'header_name' => env('TENANT_HEADER', 'X-Tenant-ID'),
        'cookie_name' => env('TENANT_COOKIE', 'tenant_id'),
        
        'fallback_to_central' => false,
    ],

    /**
     * 🚦 Rate Limiting Configuration
     */
    'rate_limiting' => [
        'enabled' => env('RATE_LIMITING_ENABLED', true),
        'limits' => [
            'unauthenticated' => env('RATE_LIMIT_UNAUTHENTICATED', 60),
            'login_attempts'  => env('RATE_LIMIT_LOGIN', 5),
            'authenticated'   => env('RATE_LIMIT_AUTHENTICATED', 300),
            'api'             => env('RATE_LIMIT_API', 1000),
        ],
        'backoff_multiplier' => 2,
        'max_backoff_level' => 5,
    ],

    /**
     * 🧠 Anomaly Detection Configuration
     */
    'anomaly_detection' => [
        'enabled' => env('ANOMALY_DETECTION_ENABLED', true),
        // CORREGIDO: Eliminado localhost. En Render debes definir esta ENV apuntando
        // a tu servicio interno (ej: http://anomaly-service:3001) o usar null para desactivar si no está listo.
        'service_url' => env('ANOMALY_SERVICE_URL'), 
        'timeout' => 2.0,
        'thresholds' => [
            'notify' => 5.0,
            'block'  => 8.5,
        ],
    ],

    /**
     * 🔑 JWT Configuration
     */
    'jwt' => [
        'secret' => env('JWT_SECRET'), // Debe fallar si no existe
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        'ttl' => env('JWT_TTL', 60 * 15),
        'refresh_ttl' => env('JWT_REFRESH_TTL', 60 * 60 * 24 * 7),
        // CORREGIDO: Issuer apunta a tu dominio real
        'issuer' => env('APP_URL', 'https://workchain-erp.onrender.com'),
    ],

    /**
     * 🔐 Cryptography Configuration
     */
    'crypto' => [
        'password_algorithm' => 'argon2id', 
        'allowed_hashes' => ['argon2id', 'bcrypt'],
        'cipher' => 'AES-256-GCM',
    ],

    /**
     * 🌐 CORS Configuration
     * CRÍTICO: Aquí definimos quién puede conectar con tu API.
     */
    'cors' => [
        // CORREGIDO: Añadido tu dominio de Render explícitamente para evitar bloqueos
        'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'https://workchain-erp.onrender.com'))),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Tenant-ID', 'X-Requested-With'],
        'expose_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
        'max_age' => 3600,
        'supports_credentials' => true,
    ],

    /**
     * 🛡️ Security Headers
     */
    'headers' => [
        'hsts' => [
            'enabled' => env('HSTS_ENABLED', true),
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true,
        ],
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;",
    ],

    /**
     * 📜 Audit Logging
     */
    'audit' => [
        'enabled' => env('AUDIT_LOGGING_ENABLED', true),
        'driver' => 'database',
        'retention_days' => 365,
        'events' => [
            'auth.login', 'auth.failed', 'auth.logout',
            'model.created', 'model.updated', 'model.deleted',
            'security.violation',
        ],
    ],

    /**
     * 🔒 Session Security
     */
    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 120),
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
        'encrypt' => env('SESSION_ENCRYPT', true),
        'files' => storage_path('framework/sessions'),
        'connection' => env('SESSION_CONNECTION', null),
        'table' => 'sessions',
        'store' => env('SESSION_DRIVER', 'database'), // En Render, usa 'database' o 'redis', nunca 'file' si usas réplicas
        'lottery' => [2, 100],
        'cookie' => env('SESSION_COOKIE', 'workchain_session'),
        'path' => '/',
        // CORREGIDO: Dejar null permite que Laravel detecte el dominio automáticamente (más seguro en Render)
        'domain' => env('SESSION_DOMAIN', null),
        'secure' => env('SESSION_SECURE_COOKIE', true), // Siempre true en Render (HTTPS)
        'http_only' => true,
        'same_site' => 'lax', // 'lax' es mejor para el flujo de login que 'strict'
    ],
];