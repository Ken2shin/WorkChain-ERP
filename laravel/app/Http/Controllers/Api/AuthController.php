<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Services\JWTService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends ApiController
{
    private JWTService $jwtService;
    private AuditLogger $auditLogger;

    public function __construct(JWTService $jwtService, AuditLogger $auditLogger)
    {
        $this->jwtService = $jwtService;
        $this->auditLogger = $auditLogger;
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $user = User::where('email', $validated['email'])
            ->where('tenant_id', $validated['tenant_id'])
            ->first();

        if (!$user) {
            $this->auditLogger->logLoginAttempt($validated['email'], false, 'user_not_found');
            return $this->unauthorized('Invalid credentials');
        }

        if (!$user->is_active) {
            $this->auditLogger->logLoginAttempt($validated['email'], false, 'account_inactive');
            return $this->unauthorized('Account is inactive');
        }

        if (!Hash::check($validated['password'], $user->password)) {
            $this->auditLogger->logLoginAttempt($validated['email'], false, 'invalid_credentials');
            return $this->unauthorized('Invalid credentials');
        }

        // Generar tokens JWT
        $accessToken = $this->jwtService->generateToken([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
            'role' => $user->role,
        ], 24 * 60); // 24 horas

        $refreshToken = $this->jwtService->generateRefreshToken([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        // Actualizar último login
        $user->updateLastLogin();

        // Log del evento
        $this->auditLogger->logLoginAttempt($validated['email'], true);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 86400,
        ], 'Login successful');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'tenant_code' => 'required|string',
        ]);

        $tenant = Tenant::where('slug', $validated['tenant_code'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return $this->error('Tenant not found', null, 404);
        }

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role' => 'user',
                'is_active' => false, // Requiere aprobación
            ]);

            $this->auditLogger->logAction('user_registered', 'user', $user->id);

            return $this->success([
                'user_id' => $user->id,
                'message' => 'Registration successful. Waiting for admin approval.'
            ], 'User registered', 201);
        } catch (\Exception $e) {
            Log::error('Registration error', ['error' => $e->getMessage()]);
            return $this->error('Registration failed', null, 500);
        }
    }

    public function refresh(Request $request)
    {
        $validated = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $payload = $this->jwtService->verifyToken($validated['refresh_token']);

        if (!$payload || $this->jwtService->isTokenExpired($payload)) {
            return $this->unauthorized('Invalid or expired refresh token');
        }

        $user = User::find($payload['user_id']);

        if (!$user || !$user->is_active) {
            return $this->unauthorized('User not found or inactive');
        }

        $newAccessToken = $this->jwtService->generateToken([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
            'role' => $user->role,
        ], 24 * 60);

        return $this->success([
            'access_token' => $newAccessToken,
            'expires_in' => 86400,
        ], 'Token refreshed');
    }

    public function logout(Request $request)
    {
        $this->auditLogger->logAction('logout', 'auth', null);

        return $this->success(null, 'Logout successful');
    }

    public function me(Request $request)
    {
        if (!$request->user()) {
            return $this->unauthorized('Not authenticated');
        }

        $user = $request->user();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tenant_id' => $user->tenant_id,
            'permissions' => $user->permissions,
        ]);
    }
}
