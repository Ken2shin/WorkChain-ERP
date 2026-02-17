<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController; // Aseguramos herencia correcta
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
     * Login unificado y robusto.
     * Soporta login con o sin tenant_id explícito.
     */
    public function login(Request $request)
    {
        // 1. Validación combinada (Acepta tenant_id opcional como en el Código A)
        $validated = $request->validate([
            'email'     => 'required|email|max:255',
            'password'  => 'required|min:8',
            'tenant_id' => 'nullable|string|uuid|exists:tenants,id',
        ]);

        try {
            // 2. Búsqueda del usuario ignorando el Global Scope de Tenant
            // Usamos withoutGlobalScopes() que es el método nativo de Laravel
            $query = User::withoutGlobalScopes();

            if (!empty($validated['tenant_id'])) {
                // Si el request trae tenant_id, filtramos específicamente
                $user = $query->where('email', $validated['email'])
                              ->where('tenant_id', $validated['tenant_id'])
                              ->first();
            } else {
                // Si no trae tenant_id, buscamos solo por email (Lógica Código B)
                $user = $query->where('email', $validated['email'])->first();
            }

            // 3. Verificación de existencia
            if (!$user) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'user_not_found', $request->ip());
                return $this->unauthorized('Invalid credentials');
            }

            // 4. Verificación de estado (Compatibilidad con ambos códigos: boolean o string)
            // Asumimos que si is_active es false O status no es 'active', la cuenta está inactiva
            $isInactive = (isset($user->is_active) && !$user->is_active) || 
                          (isset($user->status) && $user->status !== 'active');

            if ($isInactive) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'account_inactive', $request->ip());
                return $this->unauthorized('Account is inactive');
            }

            // 5. Verificación de contraseña segura
            if (!Hash::check($validated['password'], $user->password)) {
                $this->auditLogger->logLoginAttempt($validated['email'], false, 'invalid_password', $request->ip());
                return $this->unauthorized('Invalid credentials');
            }

            // 6. Inyección de contexto Tenant (Vital para el resto del request)
            app()->instance('tenant_id', $user->tenant_id);

            // 7. Generación de Tokens
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

            // Actualizar último login si el método existe (Seguridad Código B)
            if (method_exists($user, 'updateLastLogin')) {
                $user->updateLastLogin();
            }

            // 8. Log de éxito
            $this->auditLogger->logLoginAttempt($validated['email'], true, 'success', $request->ip());

            // 9. Retorno de respuesta JSON estricta (Headers de seguridad del Código A)
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
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
                ]
            ], 200, [
                'Content-Type' => 'application/json',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
            ]);

        } catch (\Throwable $e) { // Usamos Throwable para capturar errores fatales también (Código B)
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'data'    => null
            ], 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Registro de usuarios (Del Código A - Funcionalidad completa)
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users',
            'password'    => 'required|min:8|confirmed',
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
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role'      => 'user',
                'is_active' => false, // Requiere aprobación
                'status'    => 'pending', // Añadido para compatibilidad con ambos esquemas
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
     * Refrescar Token (Del Código A - Funcionalidad completa)
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

        // Verificación de estado robusta
        $isInactive = !$user || 
                      (isset($user->is_active) && !$user->is_active) || 
                      (isset($user->status) && $user->status !== 'active');

        if ($isInactive) {
            return $this->unauthorized('User not found or inactive');
        }

        // Re-inyectar tenant para seguridad
        app()->instance('tenant_id', $user->tenant_id);

        $newAccessToken = $this->jwtService->generateToken([
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'email'     => $user->email,
            'role'      => $user->role,
        ], 24 * 60); // Asumimos 24 horas si no está en config

        return $this->success([
            'access_token' => $newAccessToken,
            'expires_in'   => 86400,
        ], 'Token refreshed');
    }

    /**
     * Logout (Del Código A)
     */
    public function logout(Request $request)
    {
        $this->auditLogger->logAction('logout', 'auth', null);
        return $this->success(null, 'Logout successful');
    }

    /**
     * Obtener usuario actual (Del Código A)
     */
    public function me(Request $request)
    {
        if (!$request->user()) {
            return $this->unauthorized('Not authenticated');
        }

        $user = $request->user();

        return $this->success([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role,
            'tenant_id'   => $user->tenant_id,
            'permissions' => $user->permissions ?? [], // Null coalescing por seguridad
        ]);
    }
}