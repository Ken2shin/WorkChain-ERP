<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class PermissionGuard
{
    /**
     * Definición base de roles. 
     * NOTA: En un ERP real, esto debería cargarse desde la Base de Datos (tabla roles_permissions)
     * para permitir roles dinámicos por Tenant.
     */
    private array $defaultRolePermissions = [
        'tenant_admin' => ['*'], // Admin de la organización (NO Super Admin del sistema)
        'manager' => [
            'view_reports',
            'manage_users',
            'manage_departments',
            'approve_expenses',
            'manage_projects'
        ],
        'user' => [
            'view_own_data',
            'submit_expense',
            'view_projects',
            'submit_timesheet'
        ],
        'guest' => [
            'view_own_data'
        ]
    ];

    /**
     * Verifica si el usuario puede realizar una acción.
     * Incluye validación estricta de aislamiento de Tenant.
     */
    public function can(string $permission): bool
    {
        $user = Auth::user();

        // 1. Verificar autenticación básica
        if (!$user) {
            return false;
        }

        // 2. SEGURIDAD BRUTAL: Validación de Contexto (Tenant Isolation)
        // Si el usuario intenta actuar en un tenant que no es el suyo, BLOQUEAR.
        // Esto previene que un admin de la Empresa A apruebe gastos en la Empresa B.
        $currentTenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        
        if ($currentTenantId && $user->tenant_id !== $currentTenantId) {
            // Loguear intento de violación de acceso cruzado si tienes el AuditLogger
            // app(AuditLogger::class)->logAccessDenied($permission, 'cross_tenant_access_attempt');
            return false;
        }

        // 3. Verificar estado de la cuenta
        if (!$user->is_active) {
            return false;
        }

        // 4. Super Admin del Sistema (Acceso Global, fuera de tenants)
        // Asumimos que tienes un rol especial o un flag is_super_admin
        if ($user->role === 'super_admin') {
            return true;
        }

        // 5. Tenant Admin (Acceso total SOLO dentro de su tenant)
        if ($user->role === 'tenant_admin') {
            return true;
        }

        // 6. Permisos Específicos (Granularidad)
        $userPermissions = $user->permissions ?? []; // Permisos ad-hoc en JSON column

        // A. Permiso directo asignado al usuario
        if (is_array($userPermissions) && in_array($permission, $userPermissions, true)) {
            return true;
        }

        // B. Permiso vía Rol
        $rolePerms = $this->getPermissionsForRole($user->role);

        // Comodín de rol (*)
        if (in_array('*', $rolePerms, true)) {
            return true;
        }

        return in_array($permission, $rolePerms, true);
    }

    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }

    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            // Registrar auditoría de fallo
            if (class_exists(\App\Services\AuditLogger::class)) {
                app(\App\Services\AuditLogger::class)->logAccessDenied($permission, 'unauthorized');
            }
            
            throw new AuthorizationException('This action is unauthorized or context is invalid.');
        }
    }

    /**
     * Obtiene todos los permisos efectivos del usuario actual.
     * Útil para enviar al Frontend (Vue/React) para ocultar botones.
     */
    public function getUserPermissions(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        // Validación de contexto nuevamente para no filtrar datos de permisos incorrectos
        $currentTenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        if ($currentTenantId && $user->tenant_id !== $currentTenantId) {
            return [];
        }

        if ($user->role === 'super_admin' || $user->role === 'tenant_admin') {
            return ['*']; 
        }

        $rolePerms = $this->getPermissionsForRole($user->role);
        $customPerms = $user->permissions ?? [];

        // Eliminar duplicados y reindexar
        return array_values(array_unique(array_merge($rolePerms, $customPerms)));
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->can($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Helper para obtener permisos de rol de forma segura.
     */
    private function getPermissionsForRole(?string $role): array
    {
        return $this->defaultRolePermissions[$role] ?? [];
    }
}