<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class PermissionGuard
{
    private array $rolePermissions = [
        'admin' => ['*'],
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

    public function can(string $permission): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Super admins pueden hacer todo
        if ($user->role === 'admin') {
            return true;
        }

        $userPermissions = $user->permissions ?? [];

        // Si el usuario tiene permisos personalizados
        if (is_array($userPermissions) && in_array($permission, $userPermissions)) {
            return true;
        }

        // Verificar permisos por rol
        $rolePerms = $this->rolePermissions[$user->role] ?? [];

        if (in_array('*', $rolePerms)) {
            return true;
        }

        return in_array($permission, $rolePerms);
    }

    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }

    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'This action is unauthorized.'
            );
        }
    }

    public function getUserPermissions(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        if ($user->role === 'admin') {
            return array_merge(['*'], array_merge(...array_values($this->rolePermissions)));
        }

        $rolePerms = $this->rolePermissions[$user->role] ?? [];
        $customPerms = $user->permissions ?? [];

        return array_merge($rolePerms, $customPerms);
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
}
