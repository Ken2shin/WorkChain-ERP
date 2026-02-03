<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    // Rutas públicas (sin autenticación)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Rutas protegidas (requieren autenticación)
    Route::middleware('auth:jwt')->group(function () {
        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

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

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now(),
        'service' => 'WorkChain ERP API',
    ]);
});
