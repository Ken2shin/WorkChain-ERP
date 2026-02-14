<?php

namespace App\Models;

// Traits de Laravel y Seguridad
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Recomendado para API/ERP
use App\Traits\BelongsToTenant;   // <--- CRÍTICO: Tu filtro de seguridad

class User extends Authenticatable
{
    /* * INYECCIÓN DE SEGURIDAD:
     * 'BelongsToTenant' asegura que NUNCA se consulte un usuario
     * fuera del tenant actual (evita cruce de datos en Login).
     */
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant;

    // SEGURIDAD: Solo permitir editar estos campos explícitamente.
    // Jamás permitir que 'tenant_id' o 'role' se inyecten sin control.
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',         // Validar estrictamente en el Controller/Request
        'tenant_id',    // Validar que coincida con el contexto
        'permissions',
        'requires_2fa',
        'last_login_at',
        'last_ip_address'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        '2fa_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'requires_2fa'      => 'boolean',
        'permissions'       => 'array',
        'password'          => 'hashed', // Laravel 10+: Hasheo automático seguro
    ];

    // --- Relaciones ---

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // --- Métodos de Lógica de Negocio ---

    /**
     * Verifica permisos con jerarquía (Admin tiene todo).
     */
    public function hasPermission(string $permission): bool
    {
        // Fail-safe: Si el usuario está desactivado, no tiene permisos.
        if (!$this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager' || $this->isAdmin();
    }

    public function updateLastLogin(string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_ip_address' => $ip // Útil para auditoría de seguridad
        ]);
    }

    // --- Configuración del Trait BelongsToTenant ---

    // Define explícitamente la columna para evitar ambigüedades
    public function getTenantIdColumn(): string
    {
        return 'tenant_id';
    }
}