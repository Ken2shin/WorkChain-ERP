<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Todas las rutas son stateless (sin sesión) y devuelven JSON.
| Se utiliza el prefijo de versión para evitar conflictos futuros.
*/

// Obtenemos la versión desde la config, o usamos 'v1' por defecto
$apiVersion = config('api-security.versioning.current_version', 'v1');

Route::prefix($apiVersion)->middleware(['api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 🔓 RUTAS PÚBLICAS (No requieren Token)
    |--------------------------------------------------------------------------
    */
    
    // 1. Autenticación y Registro
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');

    // 2. Tenants (Necesario para el dropdown del login)
    // Se asume que TenantController existe. Si no, comenta esta línea.
    if (class_exists(TenantController::class)) {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    }

    /*
    |--------------------------------------------------------------------------
    | 🔒 RUTAS PROTEGIDAS (Requieren JWT Valido)
    |--------------------------------------------------------------------------
    | El middleware 'jwt.auth' verifica que el token sea válido.
    | Si falla, devuelve 401 Unauthorized (JSON), NO redirige.
    */
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 1. Gestión de Sesión
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // 2. Módulos del ERP (Placeholders listos para descomentar)
        
        // Inventario
        // Route::apiResource('products', ProductController::class);
        // Route::apiResource('inventory', InventoryController::class);

        // Ventas
        // Route::apiResource('sales-orders', SalesOrderController::class);

        // RRHH
        // Route::apiResource('employees', EmployeeController::class);

        // Finanzas
        // Route::apiResource('invoices', InvoiceController::class);
    });

});

/*
|--------------------------------------------------------------------------
| 🩺 HEALTH CHECKS (Vital para Render)
|--------------------------------------------------------------------------
| Estas rutas no tienen prefijo de versión para ser accesibles fácilmente
| por los balanceadores de carga.
*/

// Check simple de que el servidor responde
Route::get('/health', function () {
    return response()->json([
        'status' => 'running',
        'service' => 'WorkChain ERP API',
        'timestamp' => now()->toIso8601String(),
        'version' => app()->version(),
    ]);
});

// Check profundo (Verifica conexión a Base de Datos)
Route::get('/up', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'up', 
            'database' => 'connected'
        ], 200);
    } catch (\Throwable $e) {
        // Retornamos 503 para que Render sepa que el servicio no está listo
        return response()->json([
            'status' => 'down', 
            'database' => 'disconnected',
            'error' => config('app.debug') ? $e->getMessage() : 'Database connection error'
        ], 503);
    }
});