<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuditLogger
{
    public function logAction(
        string $action,
        string $resourceType = null,
        int $resourceId = null,
        array $metadata = []
    ): void {
        $user = Auth::user();
        $tenantId = app('tenant_id') ?? ($user?->tenant_id);

        if (!$tenantId) {
            return;
        }

        SecurityAuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'is_flagged' => $this->shouldFlag($action, $metadata),
            'flag_reason' => $this->getFlagReason($action, $metadata),
        ]);
    }

    public function logLoginAttempt(string $email, bool $success, string $reason = null): void
    {
        $tenantId = app('tenant_id');

        $metadata = [
            'email' => $email,
            'success' => $success,
            'reason' => $reason,
        ];

        $flag = !$success && in_array($reason, ['invalid_credentials', 'account_locked']);

        SecurityAuditLog::create([
            'tenant_id' => $tenantId,
            'action' => 'login_attempt',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'is_flagged' => $flag,
            'flag_reason' => $flag ? $reason : null,
        ]);
    }

    public function logAccessDenied(string $resource, string $reason = null): void
    {
        $user = Auth::user();
        $tenantId = app('tenant_id') ?? ($user?->tenant_id);

        if (!$tenantId) {
            return;
        }

        SecurityAuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'action' => 'permission_denied',
            'resource_type' => $resource,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => ['reason' => $reason],
            'is_flagged' => true,
            'flag_reason' => 'unauthorized_access_attempt',
        ]);
    }

    private function shouldFlag(string $action, array $metadata): bool
    {
        $flaggableActions = [
            'permission_denied',
            'anomaly',
            'bulk_delete',
            'role_change',
            'permission_grant',
        ];

        return in_array($action, $flaggableActions);
    }

    private function getFlagReason(string $action, array $metadata): ?string
    {
        return match ($action) {
            'permission_denied' => 'unauthorized_access_attempt',
            'anomaly' => $metadata['anomaly_type'] ?? 'suspicious_behavior',
            'bulk_delete' => 'bulk_delete_operation',
            'role_change' => 'role_modification',
            'permission_grant' => 'permission_escalation',
            default => null,
        };
    }
}
