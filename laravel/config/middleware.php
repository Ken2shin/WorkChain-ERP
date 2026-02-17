<?php

/**
 * Configuración de Middleware para Laravel 11
 * SOLUCION: Registra el middleware JWT personalizado
 */

return [
    // Alias de middleware para usar en rutas
    'aliases' => [
        'jwt.auth' => \App\Http\Middleware\JwtAuthMiddleware::class,
        'secure-api' => \App\Http\Middleware\SecureApiHeaders::class,
    ],

    // Stack de middleware global
    'http' => [
        // Middlewares globales que se ejecutan en cada request
        // Se registran en app/Http/Middleware si es necesario
    ],

    // Middleware para rutas API
    'api' => [
        'throttle:api',
        'bindings',
    ],
];
