<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SOLUCION: Registrar el middleware JWT personalizad
        // Laravel 11 usa una estructura diferente para registrar middlewares
        $this->app->make('router')->aliasMiddleware('jwt.auth', \App\Http\Middleware\JwtAuthMiddleware::class);

        // SOLUCION: Registrar el guard JWT para autenticación
        // Permite que middleware('auth:jwt') funcione correctamente si se usa
        Auth::extend('jwt', function ($app, $name, array $config) {
            return new \App\Guards\JwtGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $app['services.jwt'] ?? app(\App\Services\JWTService::class)
            );
        });

        // Configurar respuestas JSON para API
        // Asegura que incluso los errores sean JSON, nunca HTML
        \Illuminate\Support\Facades\Response::macro('apiSuccess', function ($data = null, $message = 'Success', $code = 200) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
            ], $code, [
                'Content-Type' => 'application/json',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });

        \Illuminate\Support\Facades\Response::macro('apiError', function ($message = 'Error', $data = null, $code = 500) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => $data,
            ], $code, [
                'Content-Type' => 'application/json',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });
    }
}
