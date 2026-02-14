<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

/**
 * WorkChain ERP - Console Routes & Schedule
 * PHP 8.3 | Laravel 11
 */

/*
|--------------------------------------------------------------------------
| 🛠️ COMANDOS DE UTILIDAD
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quotes()->random());
})->describe('Display an inspiring quote');

/**
 * Health Check Robusto para Render
 * Verifica BD, Caché y Sistema de Archivos.
 */
Artisan::command('workchain:health', function () {
    $this->info('🛡️  WorkChain ERP System Status');
    $this->line('───────────────────────────────────');
    
    // 1. Verificar Base de Datos (Supabase)
    try {
        $pdo = DB::connection()->getPdo();
        $this->info("✓ Database: Connected (Supabase)");
    } catch (\Exception $e) {
        $this->error("✗ Database: FAILED - " . $e->getMessage());
        return 1; // Exit code error
    }

    // 2. Verificar Entorno
    $this->info("✓ Environment: " . app()->environment());
    $this->info("✓ App URL: " . config('app.url')); // Verificar que sea la de Render

    // 3. Verificar Tenants Activos
    // Usamos withoutGlobalScopes porque en consola no hay contexto HTTP
    $count = Tenant::withoutGlobalScopes()->where('is_active', true)->count();
    $this->info("✓ Active Tenants: $count");

    $this->line('───────────────────────────────────');
    $this->comment('System Operational');
})->describe('Check ERP connection status and active tenants');

/*
|--------------------------------------------------------------------------
| 🕒 PROGRAMADOR DE TAREAS (SCHEDULE)
|--------------------------------------------------------------------------
| Esto es CRÍTICO para que el login no se vuelva lento con el tiempo.
| Limpia tokens expirados y logs viejos automáticamente.
|
| NOTA: En Render, debes configurar el "Cron Job" para que ejecute:
| php artisan schedule:run
*/

Schedule::call(function () {
    // 1. Limpiar Tokens de Password Reset expirados
    DB::table('password_reset_tokens')->where('created_at', '<', now()->subHours(24))->delete();
    
    // 2. Limpiar Sesiones de Base de Datos antiguas (si usas driver 'database')
    // Esto previene que la tabla sessions explote en tamaño.
    DB::table('sessions')->where('last_activity', '<', now()->subDays(30)->getTimestamp())->delete();

})->daily()->name('cleanup_auth_tokens');

// Limpiar Logs de Auditoría antiguos (Retención de 1 año)
// Vital para no saturar el almacenamiento de Supabase.
Schedule::command('model:prune', [
    '--model' => [\App\Models\SecurityAuditLog::class],
])->dailyAt('02:00');