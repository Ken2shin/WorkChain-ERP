<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes Configuration
|--------------------------------------------------------------------------
| Arquitectura: Multi-Tenant por Subdominio.
| Todas las rutas aquí asumen que el middleware 'IdentifyTenant' ya se ejecutó
| (configurado en bootstrap/app.php) y el contexto de la organización existe.
*/

// Versión de la API (Hardcoded o config, pero simple es mejor para mantener)
$v1 = 'v1';

Route::prefix($v1)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 🔓 RUTAS PÚBLICAS (Contexto Tenant)
    |--------------------------------------------------------------------------
    | Rutas accesibles sin token, pero protegidas por WAF y Rate Limiting.
    */
    
    // 1. LOGIN & AUTENTICACIÓN
    // Aplicamos 'throttle:login_attempts' (definido en config/security.php)
    // para evitar fuerza bruta extrema.
    Route::middleware(['throttle:rate_limiting.limits.login_attempts'])->group(function () {
        
        // El Login recibe el contexto del Tenant automáticamente por el subdominio.
        // No hace falta pasar 'tenant_id' en el body, el middleware lo inyecta.
        Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
        
        // Refresh token (útil si el access token expiró)
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        
        // Recuperación de contraseña
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot');
    });

    /*
    |--------------------------------------------------------------------------
    | 🔒 RUTAS PROTEGIDAS (Requieren JWT válido)
    |--------------------------------------------------------------------------
    | Aquí reside la lógica del ERP. El usuario ya está autenticado y
    | vinculado a su organización.
    */
    Route::middleware(['auth:api', 'throttle:rate_limiting.limits.api'])->group(function () {
        
        // Perfil y Logout
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // ==========================================
        // MÓDULOS DEL ERP (Descomentar al implementar)
        // ==========================================
        
        // Inventario & Almacenes
        // Route::apiResource('products', ProductController::class);
        // Route::apiResource('warehouses', WarehouseController::class);
        // Route::get('inventory/movements', [InventoryController::class, 'movements']);

        // Ventas & CRM
        // Route::apiResource('customers', CustomerController::class);
        // Route::apiResource('sales-orders', SalesOrderController::class);
        
        // RRHH
        // Route::apiResource('employees', EmployeeController::class);
    });
});

/*
|--------------------------------------------------------------------------
| 🏥 HEALTH CHECKS & MONITORING
|--------------------------------------------------------------------------
| Endpoints para Render/Kubernetes. NO exponen versiones específicas.
*/

Route::get('/health', function () {
    // Respuesta JSON ligera para el Load Balancer
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
    ], 200);
});

// Endpoint seguro para verificar conexión a BD (útil para debugging interno)
Route::get('/up', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json(['db' => 'connected', 'status' => 'up']);
    } catch (\Exception $e) {
        return response()->json(['db' => 'disconnected', 'status' => 'down'], 503);
    }
});