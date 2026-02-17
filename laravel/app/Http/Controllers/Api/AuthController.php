<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Models\Tenant;
use App\Services\JWTService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class AuthController extends ApiController
{
    private JWTService $jwtService;
    private AuditLogger $auditLogger;

    public function __construct(JWTService $jwtService, AuditLogger $auditLogger)
    {
        $this->jwtService   = $jwtService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * LOGIN (SIN tenant_id)
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|min:8',
        ]);

        try {
            /**
             * 🔑 CLAVE:
             * Se desactiva el GlobalScope SOLO aquí
             */
            $user = User::withoutTenant()
                ->where('email', $validated['email'])
                ->first();

            if (!$user) {
                $this->auditLogger->logLoginAttempt(
                    $validated['email'],
                    false,
                    'user_not_found',
                    $request->ip()
                );

                return $this->unauthorized('Invalid credentials');
            }

            if ($user->status !== 'active') {
    return $this->unauthorized('Account is inactive');
}


            if (!Hash::check($validated['password'], $user->password)) {
                return $this->unauthorized('Invalid credentials');
            }

            /**
             * Inyectamos tenant_id al contenedor
             * para el resto del ciclo de vida
             */
            App::instance('tenant_id', $user->tenant_id);

            $accessToken = $this->jwtService->generateToken([
                'user_id'   => $user->id,
                'tenant_id' => $user->tenant_id,
                'email'     => $user->email,
                'role'      => $user->role,
            ], config('api-security.jwt.expiration_minutes'));

            $refreshToken = $this->jwtService->generateRefreshToken([
                'user_id'   => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            if (method_exists($user, 'updateLastLogin')) {
                $user->updateLastLogin();
            }

            $this->auditLogger->logLoginAttempt(
                $validated['email'],
                true,
                'success',
                $request->ip()
            );

            return $this->success([
                'user' => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'role'      => $user->role,
                    'tenant_id' => $user->tenant_id,
                ],
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => config('api-security.jwt.expiration_minutes') * 60,
            ], 'Login successful');

        } catch (\Throwable $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
            ]);

            return $this->error('Authentication failed', null, 500);
        }
    }
}
