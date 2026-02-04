<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

/**
 * WorkChain ERP - Web Routes
 * PHP 8.3 | Laravel 11
 * * SECURITY: NO hardcoded URLs - all redirects use environment variables
 * Routes redirect to frontend Astro app (separate from this API)
 */

// Landing page - redirects to frontend home
Route::get('/', function () {
    // Se elimina el valor por defecto. Es OBLIGATORIO definir FRONTEND_URL en el .env
    $frontendUrl = rtrim(env('FRONTEND_URL'), '/');
    return redirect($frontendUrl . '/');
})->name('home');

// Login route - redirects to frontend login page
// This ensures users go to Astro frontend, not Laravel
Route::get('/login', function () {
    $frontendUrl = rtrim(env('FRONTEND_URL'), '/');
    // Redirige estrictamente a la ruta definida en la variable de entorno
    return redirect($frontendUrl . '/login');
})->name('login');

// Dashboard route - redirects to frontend dashboard
Route::get('/dashboard', function () {
    $frontendUrl = rtrim(env('FRONTEND_URL'), '/');
    return redirect($frontendUrl . '/dashboard');
})->name('dashboard');

// Rutas API de autenticación (estas se llaman desde Astro)
Route::middleware(['api'])->prefix('api')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'apiLogin']);
    Route::post('/auth/logout', [AuthController::class, 'apiLogout']);
    Route::get('/auth/me', [AuthController::class, 'getUser']);
});

// Health checks
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'service' => 'WorkChain ERP Web',
        'timestamp' => now()->toIso8601String(),
        'version' => app()->version(),
    ]);
})->name('health');

// Health check simple response for Koyeb monitoring
Route::get('/health/simple', function () {
    return response()->text('OK');
});

// Rutas protegidas por autenticación
// NOTA: Estas rutas manejan la lógica interna, pero si la UI está en Astro,
// Astro debe consumir la API, no estas rutas web directamente, a menos que sean híbridas.
Route::middleware(['auth'])->group(function () {
    
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
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