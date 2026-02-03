<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:jwt')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('products', ProductController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('inventory', InventoryController::class);

        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('sales-orders', SalesOrderController::class);
        Route::apiResource('invoices', InvoiceController::class);

        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchase-orders', PurchaseOrderController::class);

        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('departments', DepartmentController::class);

        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('tasks', TaskController::class);

        Route::apiResource('shipments', ShipmentController::class);
        Route::apiResource('vehicles', VehicleController::class);

        Route::apiResource('invoices-paid', InvoicePaidController::class);
        Route::apiResource('expenses', ExpenseController::class);

        Route::apiResource('documents', DocumentController::class);
    });
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toIso8601String(),
        'service' => 'WorkChain ERP API',
        'version' => app()->version(),
    ]);
});

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