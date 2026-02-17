<?php

namespace App\Models;

// Imports esenciales de Laravel
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Importamos el Trait para el aislamiento de Inquilinos (Multi-tenancy)
// Asegúrate de que este archivo exista en app/Traits/BelongsToTenant.php
use App\Traits\BelongsToTenant; 

class User extends Authenticatable
{
    /**
     * Traits activos:
     * - HasFactory: Para crear datos de prueba (seeders).
     * - Notifiable: Para enviar correos (reset password, alertas).
     * - BelongsToTenant: Aplica el GlobalScope para aislar datos por cliente.
     */
    use HasFactory, Notifiable, BelongsToTenant;

    protected $table = 'users';

    /**
     * 🛡️ SEGURIDAD: Fillable (Lista blanca)
     * Es más seguro que $guarded. Define qué campos se pueden guardar masivamente.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',          // 'admin', 'manager', 'user'
        'tenant_id',     // Vinculación con la organización
        'permissions',   // Array JSON de permisos específicos
        'is_active',     // Boolean para bloquear acceso rápidamente
        'status',        // String para estados complejos ('active', 'pending', 'banned')
        'requires_2fa',  
        'two_factor_secret',
        'last_login_at',
        'last_ip_address',
    ];

    /**
     * 🔒 CAMPOS OCULTOS
     * Nunca se envían en las respuestas JSON (API).
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret', // Estándar Laravel
        '2fa_secret',        // Compatibilidad legacy
    ];

    /**
     * ⚡ CASTS (Conversión de tipos)
     * Transforman los datos automáticamente al leer/escribir en la BD.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'requires_2fa'      => 'boolean',
        'permissions'       => 'array',   // Convierte JSON de BD a Array PHP
        'password'          => 'hashed',  // Hashea automáticamente al guardar
    ];

    /* =====================================================
     | RELACIONES
     ===================================================== */

    /**
     * Relación: Un usuario pertenece a un Tenant (Inquilino/Empresa).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /* =====================================================
     | LÓGICA DE PERMISOS Y ROLES
     ===================================================== */

    /**
     * Verifica si el usuario tiene un permiso específico.
     * 1. Si está inactivo -> Falso.
     * 2. Si es Admin -> Verdadero (Superusuario).
     * 3. Si no, busca en su lista de permisos.
     */
    public function hasPermission(string $permission): bool
    {
        // Seguridad: Usuario inactivo no hace nada
        if (!$this->is_active) {
            return false;
        }

        // Admin tiene acceso total
        if ($this->isAdmin()) {
            return true;
        }

        // Verificar array de permisos (Null coalescing para evitar errores)
        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * Verifica si es Administrador del Tenant.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verifica si es Manager o superior.
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'admin'], true);
    }

    /* =====================================================
     | AUDITORÍA Y SEGURIDAD
     ===================================================== */

    /**
     * Actualiza la fecha e IP del último acceso.
     * Compatible con el AuthController que generamos.
     */
    public function updateLastLogin(?string $ip = null): void
    {
        $this->forceFill([
            'last_login_at'   => now(),
            'last_ip_address' => $ip,
        ])->save();
    }

    /* =====================================================
     | CONFIGURACIÓN DE MULTI-TENANCY
     ===================================================== */

    /**
     * Define explícitamente la columna de la FK del tenant.
     * Usado por el Trait BelongsToTenant.
     */
    public function getTenantIdColumn(): string
    {
        return 'tenant_id';
    }

    /**
     * Boot del Modelo
     * Se ejecuta al iniciar el modelo. Útil para valores por defecto.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generar contraseña temporal si se crea un usuario sin password (ej. invitaciones)
            if (empty($model->password)) {
                $model->password = bcrypt('ChangeMe123!');
            }
            
            // Asegurar que is_active tenga valor por defecto
            if (!isset($model->is_active)) {
                $model->is_active = true;
            }
        });
    }
}