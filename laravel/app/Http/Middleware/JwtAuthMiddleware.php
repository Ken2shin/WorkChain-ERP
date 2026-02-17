<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    private JWTService $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Middleware que verifica el JWT token en cada request
     * SOLUCION: Inyecta tenant_id al contenedor desde el token
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extraer el Bearer token del header Authorization
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid authorization header'
            ], 401, ['Content-Type' => 'application/json']);
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        // Verificar que el token sea válido
        $payload = $this->jwtService->verifyToken($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401, ['Content-Type' => 'application/json']);
        }

        // PASO CRITICO: Inyectar tenant_id al contenedor
        // Permite que el GlobalScope de BaseModel lo use en las siguientes consultas
        if (isset($payload['tenant_id'])) {
            App::instance('tenant_id', $payload['tenant_id']);
        }

        // Continuar con el siguiente middleware/controlador
        return $next($request);
    }
}
