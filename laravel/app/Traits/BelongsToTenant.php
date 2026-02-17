<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {

            // Si se desactiva explícitamente (login, sistema)
            if (App::has('ignore_tenant_scope')) {
                return;
            }

            if (Auth::check() && Auth::user()?->tenant_id) {
                $builder->where(
                    $builder->getModel()->getTenantIdColumn(),
                    Auth::user()->tenant_id
                );
            } elseif (App::has('tenant_id')) {
                $builder->where(
                    $builder->getModel()->getTenantIdColumn(),
                    App::get('tenant_id')
                );
            }
        });
    }

    public function scopeWithoutTenant(Builder $query): Builder
    {
        App::instance('ignore_tenant_scope', true);

        return $query->withoutGlobalScope('tenant');
    }
}
