<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CONFIGURACIÓN ESTRICTA DE PRODUCCIÓN
        // Eliminamos detección automática. Forzamos el dominio de Render.
        $productionHost = 'workchain-erp.onrender.com';
        
        // Subdominio para la organización demo: demo.workchain-erp.onrender.com
        $demoDomain = 'demo.' . $productionHost;

        $this->command->warn("🚨 PRODUCTION SEEDING MODE (Target: Supabase/Render)");
        $this->command->info("🏢 Target Tenant Domain: $demoDomain");

        DB::transaction(function () use ($demoDomain) {
            
            // 2. CREAR TENANT (ORGANIZACIÓN)
            // Usamos firstOrCreate para evitar duplicados en Supabase.
            $tenant = Tenant::withoutGlobalScopes()->firstOrCreate(
                ['domain' => $demoDomain], 
                [
                    'name' => 'WorkChain Corp Global',
                    'slug' => 'demo',
                    'database_name' => 'tenant_demo', 
                    'is_active' => true,
                    'plan_type' => 'enterprise',
                    'subscription_expires_at' => now()->addYears(5), 
                    'metadata' => [
                        'industry' => 'logistics',
                        'country' => 'NI',
                        'timezone' => 'America/Managua',
                    ],
                ]
            );

            // Contraseña Maestra Segura
            $securePassword = Hash::make('WorkChain2026!');

            // 3. CREAR USUARIOS
            // Usamos withoutGlobalScopes para que el seeder pueda ver y crear usuarios
            // sin que el filtro de seguridad (que espera una petición HTTP) bloquee la inserción.

            // A. Admin de la Empresa (Roberto)
            User::withoutGlobalScopes()->firstOrCreate(
                ['email' => 'admin@demo.com'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Roberto Director',
                    'password' => $securePassword,
                    'role' => 'tenant_admin', // Acceso total a la organización
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'permissions' => ['*'], 
                ]
            );

            // B. Gerente (Luci)
            User::withoutGlobalScopes()->firstOrCreate(
                ['email' => 'manager@demo.com'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Luci Gerente',
                    'password' => $securePassword,
                    'role' => 'manager', // Gestión de recursos
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'permissions' => [
                        'view_reports',
                        'manage_users',
                        'approve_expenses',
                        'manage_inventory',
                    ],
                ]
            );

            // C. Operador (Emanuel)
            User::withoutGlobalScopes()->firstOrCreate(
                ['email' => 'operador@demo.com'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Emanuel Operador',
                    'password' => $securePassword,
                    'role' => 'user', // Operativo estándar
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'permissions' => [
                        'view_own_data',
                        'submit_timesheet',
                        'view_inventory',
                    ],
                ]
            );
        });

        // 4. SALIDA DE CONFIRMACIÓN
        $this->command->info('✓ Datos de producción insertados correctamente en Supabase.');
        $this->command->line('');
        $this->command->line('──────────────────────────────────────────────────');
        $this->command->line('🌍 LOGIN URL:    https://' . $demoDomain . '/login');
        $this->command->line('──────────────────────────────────────────────────');
        $this->command->line('👤 Admin:        admin@demo.com');
        $this->command->line('👤 Manager:      manager@demo.com');
        $this->command->line('👤 User:         operador@demo.com');
        $this->command->line('🔑 Password:     WorkChain2026!');
        $this->command->line('──────────────────────────────────────────────────');
        $this->command->warn('⚠️  IMPORTANTE: Debes acceder usando el subdominio "demo."');
    }
}