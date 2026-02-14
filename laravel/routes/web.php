<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\DashboardController;

/**
 * WorkChain ERP - Web Routes
 * Arquitectura: SPA (Astro) + API (Laravel)
 * * NOTA: Las rutas de autenticación (login, logout, register)
 * YA están definidas en 'routes/api.php'. NO las redefinas aquí.
 */

/*
|--------------------------------------------------------------------------
| 🏥 Health Check Simple (Para Balanceadores de Carga)
|--------------------------------------------------------------------------
*/
Route::get('/health/simple', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});

/*
|--------------------------------------------------------------------------
| 🔒 RUTAS DEL PANEL DE CONTROL (Backend renderizado o Datos)
|--------------------------------------------------------------------------
| Estas rutas solo se acceden si el usuario tiene sesión válida Y contexto.
| El middleware 'IdentifyTenant' es CRÍTICO aquí.
*/
Route::middleware(['auth:sanctum', \App\Http\Middleware\IdentifyTenant::class])->group(function () {
    
    // Dashboard Principal (Datos JSON para el frontend)
    Route::get('/dashboard-data', [DashboardController::class, 'index'])->name('dashboard.data');
    
    // Si tu frontend (Astro) consume estos datos vía API, 
    // lo ideal es mover esta lógica a controladores API reales en routes/api.php.
    // Si estás renderizando vistas Blade parciales o algo híbrido, déjalo aquí.
    
    // Ejemplo de cómo proteger módulos por Rol (además de Tenant)
    Route::middleware('role:manager,tenant_admin')->group(function () {
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [DashboardController::class, 'finance'])->name('index');
            // ... otras rutas sensibles
        });
    });

    // ... Resto de tus módulos (Inventory, Sales, etc.)
    // Asegúrate de que DashboardController use los Modelos que ya tienen el Trait de seguridad.
});

/*
|--------------------------------------------------------------------------
| 🚀 FRONTEND FALLBACK (SPA / Astro)
|--------------------------------------------------------------------------
| Cualquier ruta que no sea API y no esté definida arriba,
| devolverá el index.html de Astro para que el router de JS (React/Vue)
| tome el control.
*/
Route::fallback(function () {
    // Ruta al archivo compilado de Astro/Vite
    $path = public_path('index.html');

    if (File::exists($path)) {
        return File::get($path);
    }

    // Respuesta JSON amigable si falta el build
    return response()->json([
        'error' => 'Frontend not built',
        'message' => 'Run "npm run build" inside your frontend folder.',
        'environment' => app()->environment()
    ], 503);
});