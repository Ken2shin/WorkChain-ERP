<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Registra una acción genérica del sistema.
     */
    public function logAction(
        string $action,
        string $resourceType = null,
        int|string $resourceId = null, // Soporte para UUIDs
        array $metadata = []
    ): void {
        // 1. Resolución Robusta del Tenant
        $user = Auth::user();
        $tenantId = app()->bound('current_tenant_id') 
            ? app('current_tenant_id') 
            : ($user?->tenant_id);

        // 2. SEGURIDAD: Si no hay tenant, es un evento de sistema crítico.
        // No podemos guardarlo en la tabla particionada por tenant, 
        // así que lo mandamos al log de archivos de emergencia.
        if (!$tenantId) {
            Log::emergency("AUDIT FAILURE: Action '$action' performed without Tenant Context.", [
                'user_id' => $user?->id,
                'ip' => request()->ip(),
                'metadata' => $metadata
            ]);
            return; 
        }

        $this->persistLog($tenantId, $user?->id, $action, $resourceType, $resourceId, $metadata);
    }

    /**
     * Registra intentos de login con inteligencia de contexto.
     */
    public function logLoginAttempt(string $email, bool $success, string $reason = null): void
    {
        // 1. Intentar obtener el tenant del contexto actual (subdominio/header)
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        // 2. Si no hay contexto (login desde raíz) pero sabemos el email, buscamos al usuario
        // para saber a qué tenant "debería" pertenecer (Auditoría Predictiva).
        if (!$tenantId && $email) {
            $user = User::where('email', $email)->first();
            $tenantId = $user?->tenant_id;
        }

        if (!$tenantId) {
            // Si sigue sin haber tenant, es un ataque a una cuenta inexistente o fuera de contexto.
            Log::warning("LOGIN ATTEMPT UNKNOWN TENANT: Email $email");
            return; 
        }

        $metadata = [
            'email' => $email,
            'success' => $success,
            'reason' => $reason,
            'endpoint' => request()->path()
        ];

        // Flaggear automáticamente si son credenciales inválidas (posible fuerza bruta)
        $isFlagged = !$success && in_array($reason, ['invalid_credentials', 'account_locked']);

        $this->persistLog(
            $tenantId, 
            null, // No tenemos User ID seguro en login fallido
            'login_attempt', 
            null, 
            null, 
            $metadata, 
            $isFlagged, 
            $isFlagged ? $reason : null
        );
    }

    public function logAccessDenied(string $resource, string $reason = null): void
    {
        $user = Auth::user();
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : $user?->tenant_id;

        if (!$tenantId) return;

        $this->persistLog(
            $tenantId,
            $user?->id,
            'permission_denied',
            $resource,
            null,
            ['reason' => $reason],
            true, // Siempre flaggear denegaciones
            'unauthorized_access_attempt'
        );
    }

    /**
     * Método centralizado de persistencia para evitar duplicidad
     */
    private function persistLog(
        string $tenantId,
        ?int $userId,
        string $action,
        ?string $resourceType,
        $resourceId,
        array $metadata,
        bool $forceFlag = false,
        ?string $flagReason = null
    ): void {
        // Enriquecer metadata con datos forenses
        $metadata['url'] = request()->fullUrl();
        $metadata['method'] = request()->method();

        // Calcular flags automáticos si no se forzaron
        $isFlagged = $forceFlag || $this->shouldFlag($action, $metadata);
        $reason = $flagReason ?? $this->getFlagReason($action, $metadata);

        SecurityAuditLog::create([
            'tenant_id'     => $tenantId,
            'user_id'       => $userId,
            'action'        => $action,
            'resource_type' => $resourceType,
            'resource_id'   => (string)$resourceId, // Castear a string por si es UUID
            'ip_address'    => request()->ip(),
            'user_agent'    => substr(request()->userAgent(), 0, 255), // Prevenir overflow
            'metadata'      => $metadata,
            'is_flagged'    => $isFlagged,
            'flag_reason'   => $reason,
        ]);
    }

    private function shouldFlag(string $action, array $metadata): bool
    {
        $flaggableActions = [
            'permission_denied',
            'anomaly_detected', // Integración con el motor de C++
            'bulk_delete',
            'role_change',
            'password_reset',
            'api_token_generated'
        ];

        return in_array($action, $flaggableActions);
    }

    private function getFlagReason(string $action, array $metadata): ?string
    {
        return match ($action) {
            'permission_denied' => 'unauthorized_access_attempt',
            'anomaly_detected' => $metadata['anomaly_type'] ?? 'suspicious_behavior',
            'bulk_delete' => 'high_impact_operation',
            'role_change' => 'privilege_escalation_risk',
            default => null,
        };
    }
}