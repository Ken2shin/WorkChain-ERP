<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| 1. Verificación de Modo Mantenimiento (Seguridad)
|--------------------------------------------------------------------------
| Si el sistema está en mantenimiento (php artisan down), carga una vista
| estática segura y detiene la ejecución para proteger la base de datos.
*/
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| 2. Carga del Autoloader de Composer
|--------------------------------------------------------------------------
| Carga todas las librerías, incluyendo JWT, Drivers de BD y tu motor de seguridad.
*/
require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| 🛡️ CRITICAL FIX: RENDER & LOAD BALANCER TRUST
|--------------------------------------------------------------------------
| Esto es VITAL para que el filtrado de Login por Organización funcione.
| Le dice a Laravel: "Confía en lo que Render dice sobre quién es el dominio".
|
| Sin esto, Request::capture() podría ver una IP interna y el sistema
| de Tenants fallaría al detectar el subdominio 'demo.'.
*/
Request::setTrustedProxies(
    ['*'], // Confiar en todos los proxies de Render (las IPs cambian dinámicamente)
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO |
    Request::HEADER_X_FORWARDED_AWS_ELB
);

/*
|--------------------------------------------------------------------------
| 3. Arranque de la Aplicación (Bootstrapping)
|--------------------------------------------------------------------------
| Inicia el framework y procesa la petición.
*/
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());