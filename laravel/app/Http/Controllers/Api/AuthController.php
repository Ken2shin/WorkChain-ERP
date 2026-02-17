<?php

namespace App\Http\Controllers\Api;

// Extendemos del ApiController que acabamos de definir arriba
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
        $this->jwtService = $jwtService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Login Unificado (Soporta tenant_id opcional)
     */
    public function login(Request $request)
    {
        // 1. Validamos los datos de entrada
        $validated = $request->validate([
            'email'     => 'required|email|max:255',
            'password'  => 'required|min:8',
            'tenant_id' => 'nullable|string|uuid|exists:tenants,id',
        ]);

        try {
            // 2. Buscamos al usuario (ignorando filtros de tenant para poder encontrarlo)
            $query = User::withoutGlobalScopes();

            if (!empty($validated['tenant_id'])) {
                // Si enviaron tenant_id, somos específicos
                $user = $query->where('email', $validated['email'])
                              ->where('tenant_id', $validated['tenant_id'])
                              ->first();
            } else {
                // Si no, buscamos por email globalmente
                $user = $query->where('email', $validated['email'])->first();
            }

            // 3. Verificamos si existe
            if (!$user) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'user_not_found', $request->ip());
                return $this->unauthorized('Invalid credentials');
            }

            // 4. Verificamos si está activo (compatible con boolean y string)
            $isInactive = (isset($user->is_active) && !$user->is_active) || 
                          (isset($user->status) && $user->status !== 'active');

            if ($isInactive) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'account_inactive', $request->ip());
                return $this->unauthorized('Account is inactive');
            }

            // 5. Verificamos contraseña
            if (!Hash::check($validated['password'], $user->password)) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'invalid_password', $request->ip());
                return $this->unauthorized('Invalid credentials');
            }

            // 6. Configuración de contexto (Vital para Multi-tenancy)
            app()->instance('tenant_id', $user->tenant_id);

            // 7. Generar Tokens
            $accessToken = $this->jwtService->generateToken([
                'user_id'   => $user->id,
                'tenant_id' => $user->tenant_id,
                'email'     => $user->email,
                'role'      => $user->role,
            ], config('api-security.jwt.expiration_minutes', 60));

            $refreshToken = $this->jwtService->generateRefreshToken([
                'user_id'   => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            // Actualizar último acceso
            if (method_exists($user, 'updateLastLogin')) {
                $user->updateLastLogin($request->ip());
            }

            // 8. Log de auditoría
            $this->auditLogger->logLoginAttempt($validated['email'], true, 'success', $request->ip());

            // 9. Respuesta exitosa usando el método del padre
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
                'expires_in'    => config('api-security.jwt.expiration_minutes', 60) * 60,
            ], 'Login successful');

        } catch (\Throwable $e) {
            // Captura de errores fatales
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip'    => $request->ip(),
            ]);

            return $this->error('Authentication failed', null, 500);
        }
    }

    /**
     * Registro de Usuarios
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users',
            'password'    => 'required|min:8|confirmed',
            'tenant_code' => 'required|string',
        ]);

        // Buscar tenant por código
        $tenant = Tenant::where('slug', $validated['tenant_code'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        try {
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role'      => 'user',
                'is_active' => false, 
                'status'    => 'pending',
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

    /**
     * Refrescar Token
     */
    public function refresh(Request $request)
    {
        $validated = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $payload = $this->jwtService->verifyToken($validated['refresh_token']);

        if (!$payload || $this->jwtService->isTokenExpired($payload)) {
            return $this->unauthorized('Invalid or expired refresh token');
        }

        $user = User::withoutGlobalScopes()->find($payload['user_id']);

        if (!$user || (isset($user->is_active) && !$user->is_active)) {
            return $this->unauthorized('User not found or inactive');
        }

        // Re-inyectar tenant
        app()->instance('tenant_id', $user->tenant_id);

        $newAccessToken = $this->jwtService->generateToken([
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'email'     => $user->email,
            'role'      => $user->role,
        ], 24 * 60); 

        return $this->success([
            'access_token' => $newAccessToken,
            'expires_in'   => 86400,
        ], 'Token refreshed');
    }

    public function logout(Request $request)
    {
        $this->auditLogger->logAction('logout', 'auth', null);
        return $this->success(null, 'Logout successful');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized();
        }

        return $this->success([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role,
            'tenant_id'   => $user->tenant_id,
            'permissions' => $user->permissions ?? [],
        ]);
    }
}