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

    /**
     * Login endpoint - Uses environment variables for all config
     * NO hardcoded URLs or endpoints
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|min:8',
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        try {
            // Find user with timing-safe authentication
            $user = User::where('email', $validated['email'])
                ->where('tenant_id', $validated['tenant_id'])
                ->first();

            if (!$user) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'user_not_found', $request->ip());
                // Generic error - don't reveal if user exists
                return $this->unauthorized('Invalid credentials');
            }

            if (!$user->is_active) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'account_inactive', $request->ip());
                return $this->unauthorized('Account is inactive');
            }

            // Timing-safe password comparison - prevents timing attacks
            if (!Hash::check($validated['password'], $user->password)) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'invalid_password', $request->ip());
                return $this->unauthorized('Invalid credentials');
            }

            // Generate JWT tokens using config-driven expiration
            $accessToken = $this->jwtService->generateToken([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
                'role' => $user->role,
            ], config('api-security.jwt.expiration_minutes'));

            $refreshToken = $this->jwtService->generateRefreshToken([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            // Update last login timestamp
            $user->updateLastLogin();

            // Log successful authentication
            $this->auditLogger->logLoginAttempt($validated['email'], true, 'success', $request->ip());

            // Return sanitized user data
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
                'expires_in' => config('api-security.jwt.expiration_minutes') * 60,
            ], 'Login successful');

        } catch (\Exception $e) {
            // Log error without exposing sensitive information
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return $this->error('Authentication failed', null, 500);
        }
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
