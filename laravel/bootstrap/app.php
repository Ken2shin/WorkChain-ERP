<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Middlewares personalizados (Asumiendo que los creaste o crearás)
use App\Http\Middleware\IdentifyTenant;       // <--- CRÍTICO para el filtrado
use App\Http\Middleware\NanoSecurityMesh;     // <--- Tu motor C++/Rust
use App\Http\Middleware\SecurityHeaders;      // Headers HSTS, CSP, etc.

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        // 1. SEGURIDAD DE RED (Vital para AuditLogger)
        // Confiar en todos los proxies (necesario en contenedores/Cloudflare)
        // para que request()->ip() sea la IP real del cliente.
        $middleware->trustProxies(at: '*');

        // 2. MIDDLEWARES GLOBALES (Se ejecutan en CADA petición)
        $middleware->append([
            SecurityHeaders::class,    // Protección XSS, Frame Options, etc.
            NanoSecurityMesh::class,   // Tu motor de amenazas (Rust/C++)
        ]);

        // 3. GRUPOS DE RUTAS
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // CRÍTICO: Identificar la organización ANTES de que el usuario intente loguearse
            IdentifyTenant::class, 
        ]);

        $middleware->group('api', [
            // 'throttle:api', // Desactivamos el throttle nativo porque usas el de Rust
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // CRÍTICO: Identificar la organización en la API también
            IdentifyTenant::class,
        ]);

        // 4. ALIAS (Para usar en rutas específicas)
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'tenant.active' => \App\Http\Middleware\EnsureTenantIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Manejo de excepciones de seguridad para no revelar stack traces
        $exceptions->render(function (\App\Exceptions\Security\TenantNotFoundException $e, Request $request) {
            return response()->json([
                'error' => 'Organization not found or inactive.',
                'code' => 'TENANT_INVALID'
            ], 404);
        });

        $exceptions->render(function (\App\Exceptions\Auth\InvalidTokenException $e, Request $request) {
            return response()->json(['error' => 'Security token violation.'], 401);
        });
    })->create();