<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

/**
 * WorkChain ERP - Web Routes
 * PHP 8.3 | Laravel 11
 * * SECURITY: NO hardcoded URLs or localhost defaults.
 * This file handles the API logic. The Frontend (Astro) handles the UI.
 */

// Landing Page de la API
// IMPORTANTE: Devolvemos JSON en lugar de redirigir para evitar el Bucle Infinito (Error 400)
Route::get('/', function () {
    return response()->json([
        'service' => 'WorkChain ERP API',
        'status' => 'Running',
        'message' => 'Access this application via the Frontend URL.',
        'frontend_url' => env('FRONTEND_URL'), // Solo informativo
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('home');

// Login Route (GET)
// Si alguien intenta entrar a /login por navegador en la API, le decimos que vaya al Frontend.
Route::get('/login', function () {
    return response()->json([
        'message' => 'Authentication is handled via API (POST). Please use the Astro Frontend to login.',
        'login_endpoint' => url('/api/auth/login')
    ], 401);
})->name('login');

// Dashboard Route (GET)
// Solo informativo para evitar errores 404 si se accede directamente
Route::get('/dashboard', function () {
    return response()->json([
        'message' => 'Dashboard is a frontend view. Please access via Astro App.'
    ]);
})->name('dashboard');

// ===== RUTAS API DE AUTENTICACIÓN (Consumidas por Astro) =====
Route::middleware(['api'])->prefix('api')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'apiLogin']);
    Route::post('/auth/logout', [AuthController::class, 'apiLogout']);
    Route::get('/auth/me', [AuthController::class, 'getUser']);
});

// Health Checks (Para monitoreo de Koyeb)
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'service' => 'WorkChain ERP Web',
        'environment' => app()->environment(),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

Route::get('/health/simple', function () {
    return response()->text('OK');
});

// ===== RUTAS PROTEGIDAS (LOGICA INTERNA) =====
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard Data
    Route::get('/dashboard-data', [DashboardController::class, 'index'])->name('dashboard.data');
    
    // ===== MÓDULO INVENTARIO =====
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [DashboardController::class, 'inventory'])->name('index');
        Route::get('/warehouses', [DashboardController::class, 'inventoryWarehouses'])->name('warehouses');
        Route::get('/products', [DashboardController::class, 'inventoryProducts'])->name('products');
        Route::get('/stock', [DashboardController::class, 'inventoryStock'])->name('stock');
    });
    
    // ===== MÓDULO VENTAS =====
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [DashboardController::class, 'sales'])->name('index');
        Route::get('/customers', [DashboardController::class, 'salesCustomers'])->name('customers');
        Route::get('/orders', [DashboardController::class, 'salesOrders'])->name('orders');
        Route::get('/invoices', [DashboardController::class, 'salesInvoices'])->name('invoices');
    });
    
    // ===== MÓDULO COMPRAS =====
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [DashboardController::class, 'purchases'])->name('index');
        Route::get('/suppliers', [DashboardController::class, 'purchasesSuppliers'])->name('suppliers');
        Route::get('/orders', [DashboardController::class, 'purchasesOrders'])->name('orders');
        Route::get('/requisitions', [DashboardController::class, 'purchasesRequisitions'])->name('requisitions');
    });
    
    // ===== MÓDULO RRHH =====
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/', [DashboardController::class, 'hr'])->name('index');
        Route::get('/employees', [DashboardController::class, 'hrEmployees'])->name('employees');
        Route::get('/payroll', [DashboardController::class, 'hrPayroll'])->name('payroll');
        Route::get('/attendance', [DashboardController::class, 'hrAttendance'])->name('attendance');
    });
    
    // ===== MÓDULO PROYECTOS =====
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [DashboardController::class, 'projects'])->name('index');
        Route::get('/list', [DashboardController::class, 'projectsList'])->name('list');
        Route::get('/tasks', [DashboardController::class, 'projectsTasks'])->name('tasks');
        Route::get('/resources', [DashboardController::class, 'projectsResources'])->name('resources');
    });
    
    // ===== MÓDULO LOGÍSTICA =====
    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/', [DashboardController::class, 'logistics'])->name('index');
        Route::get('/shipments', [DashboardController::class, 'logisticsShipments'])->name('shipments');
        Route::get('/routes', [DashboardController::class, 'logisticsRoutes'])->name('routes');
        Route::get('/tracking', [DashboardController::class, 'logisticsTracking'])->name('tracking');
    });
    
    // ===== MÓDULO FINANZAS =====
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/', [DashboardController::class, 'finance'])->name('index');
        Route::get('/accounts', [DashboardController::class, 'financeAccounts'])->name('accounts');
        Route::get('/reports', [DashboardController::class, 'financeReports'])->name('reports');
        Route::get('/budgets', [DashboardController::class, 'financeBudgets'])->name('budgets');
    });
    
    // ===== MÓDULO DOCUMENTOS =====
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DashboardController::class, 'documents'])->name('index');
        Route::get('/files', [DashboardController::class, 'documentsFiles'])->name('files');
        Route::get('/compliance', [DashboardController::class, 'documentsCompliance'])->name('compliance');
    });
    
    // ===== CONFIGURACIÓN =====
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [DashboardController::class, 'settings'])->name('index');
        Route::get('/profile', [DashboardController::class, 'settingsProfile'])->name('profile');
        Route::get('/security', [DashboardController::class, 'settingsSecurity'])->name('security');
    });
});