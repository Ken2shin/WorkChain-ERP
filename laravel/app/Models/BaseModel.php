<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // 1. Intentar obtener el Tenant ID del contexto (Middleware) o del usuario
        // Prioridad: 1. Contexto Global (Seteado por Middleware al detectar subdominio)
        //            2. Usuario Autenticado (Fallback)
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : (auth()->id() ? auth()->user()->tenant_id : null);

        // 2. SEGURIDAD BRUTAL: Evitar "Fail-Open"
        // Si no hay tenant identificado, NO mostrar nada (o solo globales).
        // Esto evita que el Login busque usuarios en toda la BD si falla el contexto.
        if (!$tenantId) {
            // Opción A: Bloquear todo si no hay contexto (Más seguro para SaaS estricto)
            // $builder->whereRaw('1 = 0'); 
            
            // Opción B: Permitir si estás en consola o es una ruta explícitamente sin tenant.
            // Para el login, el middleware DEBE haber seteado 'current_tenant_id'.
            return; 
        }

        // 3. Aplicar el filtro usando la columna dinámica
        $column = $model->getTable() . '.' . $model->getTenantIdColumn();
        $builder->where($column, $tenantId);
    }
}