<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tenants API Controller
 * 
 * Maneja operaciones CRUD para organizaciones (tenants)
 * Endpoint público: GET /api/v1/tenants (sin autenticación requerida para login)
 */
class TenantsController extends Controller
{
    /**
     * Obtiene lista de todas las organizaciones activas
     * 
     * Endpoint: GET /api/v1/tenants
     * 
     * @return JsonResponse Lista de organizaciones disponibles
     */
    public function list(): JsonResponse
    {
        try {
            // Intenta obtener del cache primero (5 minutos)
            $tenants = Cache::remember('tenants:active', 300, function () {
                return Tenant::where('is_active', true)
                    ->where('status', 'active')
                    ->select('id', 'name', 'slug', 'plan_type')
                    ->orderBy('name', 'ASC')
                    ->get()
                    ->toArray();
            });

            // Si no hay organizaciones
            if (empty($tenants)) {
                Log::warning('No active tenants found');
                return response()->json([
                    'tenants' => [],
                    'message' => 'No hay organizaciones disponibles',
                ], 200);
            }

            return response()->json([
                'tenants' => $tenants,
                'count' => count($tenants),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching tenants', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al cargar las organizaciones',
                'error' => 'internal_error',
            ], 500);
        }
    }

    /**
     * Obtiene una organización específica por ID
     * 
     * Endpoint: GET /api/v1/tenants/{id}
     * 
     * @param string $id UUID del tenant
     * @return JsonResponse Datos del tenant
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'message' => 'Organización no encontrada',
                    'error' => 'not_found',
                ], 404);
            }

            return response()->json([
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan_type' => $tenant->plan_type,
                    'max_users' => $tenant->max_users,
                    'max_storage_gb' => $tenant->max_storage_gb,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching tenant', [
                'tenant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al cargar la organización',
            ], 500);
        }
    }

    /**
     * Valida si una organización existe y está activa
     * 
     * Endpoint: POST /api/v1/tenants/validate
     * 
     * @return JsonResponse true/false si el tenant existe
     */
    public function validate(): JsonResponse
    {
        try {
            $tenantId = request()->input('tenant_id');

            if (!$tenantId) {
                return response()->json([
                    'valid' => false,
                    'message' => 'tenant_id requerido',
                ], 400);
            }

            $exists = Tenant::where('id', $tenantId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->exists();

            return response()->json([
                'valid' => $exists,
                'tenant_id' => $tenantId,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error validating tenant', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error validando organización',
            ], 500);
        }
    }

    /**
     * Limpia el cache de tenants (solo admin)
     * 
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::forget('tenants:active');

            return response()->json([
                'message' => 'Cache limpiado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error limpiando cache',
            ], 500);
        }
    }
}
