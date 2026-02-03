<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    public static function boot()
    {
        parent::boot();

        // Auto-asignar tenant_id al crear registros
        static::creating(function ($model) {
            if (method_exists($model, 'getTenantIdColumn')) {
                $tenantId = app('tenant_id') ?? auth()->user()?->tenant_id;
                if ($tenantId && !$model->{$model->getTenantIdColumn()}) {
                    $model->{$model->getTenantIdColumn()} = $tenantId;
                }
            }
        });

        // Filtrar por tenant en consultas
        static::addGlobalScope(function ($query) {
            if (method_exists($query->getModel(), 'getTenantIdColumn')) {
                $tenantId = app('tenant_id') ?? auth()->user()?->tenant_id;
                if ($tenantId) {
                    $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
                }
            }
        });
    }

    protected function getTenantIdColumn(): string
    {
        return 'tenant_id';
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
