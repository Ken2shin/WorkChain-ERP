<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes use environment variables for configuration
| NO hardcoded URLs or API endpoints in this file
|
| API versioning comes from config: api-security.versioning
*/

// Get API version from config (driven by environment variables)
$apiVersion = config('api-security.versioning.current_version', 'v1');
$apiPrefix = config('api-security.versioning.prefix', '/api/v') . $apiVersion;

Route::prefix($apiVersion)->middleware(['api', 'secure-api'])->group(function () {
    // Public routes (no authentication required)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    // Protected routes (require JWT authentication)
    Route::middleware('auth:jwt')->group(function () {
        // Auth endpoints
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Aquí irán los controladores de cada módulo
        // Inventario
        // Route::apiResource('products', ProductController::class);
        // Route::apiResource('warehouses', WarehouseController::class);
        // Route::apiResource('inventory', InventoryController::class);

        // Ventas
        // Route::apiResource('customers', CustomerController::class);
        // Route::apiResource('sales-orders', SalesOrderController::class);
        // Route::apiResource('invoices', InvoiceController::class);

        // Compras
        // Route::apiResource('suppliers', SupplierController::class);
        // Route::apiResource('purchase-orders', PurchaseOrderController::class);

        // RRHH
        // Route::apiResource('employees', EmployeeController::class);
        // Route::apiResource('departments', DepartmentController::class);

        // Proyectos
        // Route::apiResource('projects', ProjectController::class);
        // Route::apiResource('tasks', TaskController::class);

        // Logística
        // Route::apiResource('shipments', ShipmentController::class);
        // Route::apiResource('vehicles', VehicleController::class);

        // Finanzas
        // Route::apiResource('invoices-paid', InvoicePaidController::class);
        // Route::apiResource('expenses', ExpenseController::class);

        // Documentos
        // Route::apiResource('documents', DocumentController::class);
    });
});

// Health checks
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toIso8601String(),
        'service' => 'WorkChain ERP API',
        'version' => app()->version(),
    ]);
});

// Status endpoint
Route::get('/status', function () {
    return response()->json([
        'status' => 'running',
        'service' => 'ERP API',
        'timestamp' => now()->toIso8601String(),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
    ]);
});
