<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes (JSON ONLY)
|--------------------------------------------------------------------------
| Todas estas rutas devuelven JSON.
| NUNCA redirigen a /login.
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 🔓 AUTH PÚBLICO
    |--------------------------------------------------------------------------
    */
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

    /*
    |--------------------------------------------------------------------------
    | 🔒 AUTH PROTEGIDO (JWT)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['jwt.auth'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

});

/*
|--------------------------------------------------------------------------
| Health checks (Render / LB)
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () =>
    response()->json(['status' => 'ok'], 200)
);

Route::get('/up', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['db' => 'connected']);
    } catch (\Throwable $e) {
        return response()->json(['db' => 'down'], 503);
    }
});
