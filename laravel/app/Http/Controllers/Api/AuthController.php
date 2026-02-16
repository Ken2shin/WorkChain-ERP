<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JWTService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends ApiController
{
    public function __construct(
        private JWTService $jwtService,
        private AuditLogger $auditLogger
    ) {}

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        try {
            $user = User::where('email', $validated['email'])
                ->where('is_active', true)
                ->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                $this->auditLogger->logLoginAttempt(
                    $validated['email'],
                    false,
                    'invalid_credentials',
                    $request->ip()
                );

                return $this->unauthorized('Credenciales inválidas');
            }

            $accessToken = $this->jwtService->generateToken([
                'user_id'   => $user->id,
                'tenant_id' => $user->tenant_id,
                'role'      => $user->role,
            ]);

            $this->auditLogger->logLoginAttempt(
                $validated['email'],
                true,
                'success',
                $request->ip()
            );

            return $this->success([
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ],
                'access_token' => $accessToken,
            ], 'Login exitoso');

        } catch (\Throwable $e) {
            Log::error('Auth login error', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Error interno del servidor', null, 500);
        }
    }
}
