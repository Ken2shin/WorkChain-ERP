<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * TenantController
 * 
 * Controlador simple para obtener la lista de organizaciones (tenants)
 * Usado principalmente por el login para el dropdown de selección
 * 
 * Sin autenticación - Ruta Pública
 */
class TenantController extends Controller
{
    /**
     * Obtiene lista de tenants activos
     * 
     * Retorna solo id y name para el dropdown del login
     * Filtra por status = 'active' para mostrar solo organizaciones disponibles
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Obtiene tenants activos con solo id y name
            // Los UUIDs se mantienen como strings (HasUuids trait en Tenant)
            $tenants = Tenant::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tenants,
                'message' => 'Tenants obtenidos correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tenants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
