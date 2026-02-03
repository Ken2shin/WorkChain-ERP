<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Crear tenant de demostración
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Company',
                'domain' => 'demo.localhost',
                'database_name' => 'tenant_demo',
                'is_active' => true,
                'metadata' => [
                    'industry' => 'retail',
                    'employees' => 50,
                    'country' => 'ES',
                ],
            ]
        );

        // Crear usuario administrador
        User::firstOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Administrador',
                'password' => Hash::make('Admin123!@#'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'permissions' => ['*'],
            ]
        );

        // Crear usuario gerente
        User::firstOrCreate(
            ['email' => 'manager@demo.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Gerente de Ventas',
                'password' => Hash::make('Manager123!@#'),
                'role' => 'manager',
                'is_active' => true,
                'email_verified_at' => now(),
                'permissions' => [
                    'view_reports',
                    'manage_users',
                    'manage_departments',
                    'approve_expenses',
                    'manage_projects'
                ],
            ]
        );

        // Crear usuario regular
        User::firstOrCreate(
            ['email' => 'user@demo.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Usuario Estándar',
                'password' => Hash::make('User123!@#'),
                'role' => 'user',
                'is_active' => true,
                'email_verified_at' => now(),
                'permissions' => [
                    'view_own_data',
                    'submit_expense',
                    'view_projects',
                    'submit_timesheet'
                ],
            ]
        );

        $this->command->info('✓ Database seeded successfully!');
        $this->command->line('');
        $this->command->line('Login credentials:');
        $this->command->line('─────────────────────');
        $this->command->line('Email:    admin@demo.local');
        $this->command->line('Password: Admin123!@#');
        $this->command->line('Tenant:   Demo Company (demo)');
    }
}
