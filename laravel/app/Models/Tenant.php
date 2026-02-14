<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use SoftDeletes, HasUuids, HasFactory;

    // Tabla explícita (buena práctica)
    protected $table = 'tenants';

    // SEGURIDAD: Definir estrictamente qué se puede guardar.
    // Nunca usar guarded = [] en el modelo principal de la organización.
    protected $fillable = [
        'name',
        'domain',          // Crucial para identificar el tenant en el Login (ej: empresa.app.com)
        'database_name',   // Si usas bases de datos separadas (Multi-Database tenancy)
        'metadata',
        'is_active',
        'plan_type',       // Control de suscripción
        'subscription_expires_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'subscription_expires_at' => 'datetime',
    ];

    // Ocultar datos sensibles al serializar (API Responses)
    protected $hidden = [
        'database_name',
        'deleted_at'
    ];

    /* * NOTA IMPORTANTE DE ARQUITECTURA:
     * Este modelo NO debe usar el Trait 'BelongsToTenant'.
     * El Tenant es la entidad raíz. No pertenece a nadie, él ES la organización.
     */

    // --- Relaciones ---

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class);
    }

    // --- Helpers de Seguridad para el Login ---

    /**
     * Verifica si el tenant está activo y con suscripción válida.
     * Usar esto en el Middleware de Login.
     */
    public function canAccess(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            return false;
        }

        return true;
    }
}