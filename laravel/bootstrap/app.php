<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// --------------------------------------------------------------------------
// NOTA IMPORTANTE:
// He comentado los imports de tus clases personalizadas.
// NO los descomentes hasta que hayamos creado los archivos en /app/Http/Middleware
// de lo contrario, Render te dará Error 500.
// --------------------------------------------------------------------------

// use App\Http\Middleware\IdentifyTenant;   // TODO: Descomentar cuando crees el archivo
// use App\Http\Middleware\NanoSecurityMesh; // TODO: Descomentar cuando crees el archivo
// use App\Http\Middleware\SecurityHeaders;  // TODO: Descomentar cuando crees el archivo

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        // 1. SEGURIDAD DE RED (Vital para Render/Cloudflare)
        // Esto permite que Laravel confíe en el proxy de Render y obtenga la IP real.
        $middleware->trustProxies(at: '*');

        // 2. MIDDLEWARES GLOBALES
        // Se ejecutan en cada petición. Los hemos comentado para evitar el crash.
        $middleware->append([
            // \App\Http\Middleware\SecurityHeaders::class,
            // \App\Http\Middleware\NanoSecurityMesh::class,
        ]);

        // 3. GRUPOS DE RUTAS WEB
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \App\Http\Middleware\IdentifyTenant::class, // TODO: Activar luego
        ]);

        // 4. GRUPOS DE RUTAS API
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \App\Http\Middleware\IdentifyTenant::class, // TODO: Activar luego
        ]);

        // 5. ALIAS
        // Solo descomenta si los archivos existen en app/Http/Middleware/
        $middleware->alias([
            // 'role' => \App\Http\Middleware\CheckRole::class,
            // 'permission' => \App\Http\Middleware\CheckPermission::class,
            // 'tenant.active' => \App\Http\Middleware\EnsureTenantIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Dejamos esto preparado para cuando actives la seguridad
        /*
        $exceptions->render(function (\App\Exceptions\Security\TenantNotFoundException $e, Request $request) {
            return response()->json([
                'error' => 'Organization not found or inactive.',
                'code' => 'TENANT_INVALID'
            ], 404);
        });
        */
    })->create();