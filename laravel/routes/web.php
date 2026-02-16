<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Health check simple
|--------------------------------------------------------------------------
*/
Route::get('/health/simple', fn () => response('OK', 200));

/*
|--------------------------------------------------------------------------
| 🚀 SPA FALLBACK (Astro)
|--------------------------------------------------------------------------
| Cualquier ruta NO API devuelve index.html
*/
Route::fallback(function () {
    $path = public_path('index.html');

    if (File::exists($path)) {
        return response(File::get($path), 200)
            ->header('Content-Type', 'text/html');
    }

    return response()->json([
        'error' => 'Frontend not built'
    ], 503);
});
