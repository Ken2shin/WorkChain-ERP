<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Aplica automáticamente el filtro por tenant_id
     * en TODAS las consultas del modelo.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {

            // 1. Si hay usuario autenticado (JWT, session, etc.)
            if (Auth::check() && Auth::user()?->tenant_id) {
                $builder->where(
                    $builder->getModel()->getTenantIdColumn(),
                    Auth::user()->tenant_id
                );
                return;
            }

            // 2. Si existe tenant en el contenedor (login, middleware)
            if (App::has('tenant_id')) {
                $builder->where(
                    $builder->getModel()->getTenantIdColumn(),
                    App::get('tenant_id')
                );
            }
        });
    }

    /**
     * Permite desactivar el scope explícitamente (solo admin/sistema)
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
