<?php

use Illuminate\Support\Facades\Route;

/**
 * WorkChain ERP - Web Routes
 * PHP 8.3 | Laravel 11
 */

Route::get('/', function () {
    return view('welcome');
})->name('home');

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
