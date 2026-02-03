<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 1. Verificación de Modo Mantenimiento (Seguridad)
// Si el sistema está en mantenimiento, carga una vista segura y detiene todo lo demás.
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// 2. Carga del Autoloader de Composer
// Esto carga todas tus librerías de seguridad y dependencias automáticamente.
require __DIR__.'/../vendor/autoload.php';

// 3. Arranque de la Aplicación (Bootstrapping)
// Inicia el núcleo de Laravel y procesa la petición de forma segura.
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());