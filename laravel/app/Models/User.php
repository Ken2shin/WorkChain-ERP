<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\BelongsToTenant;

class User extends Authenticatable
{
    /**
     * Traits críticos:
     * - HasFactory: factories
     * - Notifiable: notificaciones
     * - BelongsToTenant: aislamiento total por tenant
     */
    use HasFactory, Notifiable, BelongsToTenant;

    /**
     * Campos editables explícitos
     * ⚠️ NO permitir mass assignment accidental
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'tenant_id',
        'permissions',
        'requires_2fa',
        'last_login_at',
        'last_ip_address',
    ];

    /**
     * Campos ocultos SIEMPRE
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Casts seguros y explícitos
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'requires_2fa'      => 'boolean',
        'permissions'       => 'array',
        'password'          => 'hashed',
    ];

    /* =====================================================
     | RELACIONES
     ===================================================== */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /* =====================================================
     | AUTORIZACIÓN Y PERMISOS
     ===================================================== */

    /**
     * Verifica permiso específico
     * - Admin tiene acceso total
     * - Usuario inactivo NO tiene permisos
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'admin'], true);
    }

    /* =====================================================
     | AUDITORÍA Y SEGURIDAD
     ===================================================== */

    /**
     * Registra login seguro
     */
    public function updateLastLogin(?string $ip = null): void
    {
        $this->forceFill([
            'last_login_at'   => now(),
            'last_ip_address' => $ip,
        ])->save();
    }

    /* =====================================================
     | MULTI-TENANCY (CRÍTICO)
     ===================================================== */

    /**
     * Evita ambigüedad en el trait BelongsToTenant
     */
    public function getTenantIdColumn(): string
    {
        return 'tenant_id';
    }
}
