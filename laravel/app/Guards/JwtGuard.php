<?php

namespace App\Guards;

use App\Services\JWTService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class JwtGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;
    protected JWTService $jwtService;

    public function __construct(
        UserProvider $provider,
        Request $request,
        JWTService $jwtService
    ) {
        $this->provider = $provider;
        $this->request = $request;
        $this->jwtService = $jwtService;
    }

    /**
     * Obtiene el usuario autenticado desde el JWT token
     */
    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $payload = $this->getPayload();

        if ($payload && isset($payload['user_id'])) {
            // SOLUCION: Inyectar tenant_id desde el token al contenedor
            // Permite que el GlobalScope funcione correctamente
            if (isset($payload['tenant_id'])) {
                App::instance('tenant_id', $payload['tenant_id']);
            }

            $this->user = $this->provider->retrieveById($payload['user_id']);
        }

        return $this->user;
    }

    /**
     * Valida que el usuario esté autenticado
     */
    public function validate(array $credentials = [])
    {
        if (!isset($credentials['token'])) {
            return false;
        }

        $payload = $this->jwtService->verifyToken($credentials['token']);

        return $payload && isset($payload['user_id']);
    }

    /**
     * Extrae el JWT del request
     */
    protected function getPayload()
    {
        // Buscar el token en el header Authorization
        $token = $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        // Verificar el token
        return $this->jwtService->verifyToken($token);
    }

    /**
     * Extrae el Bearer token del header Authorization
     */
    protected function getTokenFromRequest(): ?string
    {
        $authHeader = $this->request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7); // Remove "Bearer " prefix
    }
}
