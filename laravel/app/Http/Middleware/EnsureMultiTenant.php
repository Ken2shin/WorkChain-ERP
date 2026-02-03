<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnsureMultiTenant
{
    public function handle(Request $request, Closure $next)
    {
        // Obtener tenant del usuario autenticado o de la cabecera
        $tenantId = $this->resolveTenant($request);

        if (!$tenantId) {
            Log::warning('Tenant not resolved', [
                'ip' => $request->ip(),
                'endpoint' => $request->path()
            ]);

            return response()->json([
                'error' => 'Unauthorized tenant access'
            ], 401);
        }

        // Verificar que el usuario pertenece al tenant
        $user = Auth::user();
        if ($user && $user->tenant_id !== $tenantId) {
            Log::warning('Tenant mismatch attempt', [
                'user_id' => $user->id,
                'user_tenant' => $user->tenant_id,
                'requested_tenant' => $tenantId,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Forbidden'
            ], 403);
        }

        // Guardar el tenant ID en el contenedor de la app
        app()->instance('tenant_id', $tenantId);

        // Agregar tenant_id a la cabecera de respuesta
        $response = $next($request);
        $response->header('X-Tenant-ID', $tenantId);

        return $response;
    }

    private function resolveTenant(Request $request): ?int
    {
        // Intentar obtener del usuario autenticado
        $user = Auth::user();
        if ($user) {
            return $user->tenant_id;
        }

        // Intentar obtener de la cabecera
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId && is_numeric($tenantId)) {
            return (int) $tenantId;
        }

        // Intentar obtener del dominio
        $host = $request->getHost();
        // Implementar lógica de resolución por dominio si es necesario

        return null;
    }
}
