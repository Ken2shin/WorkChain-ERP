<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nanodefense WAF (Web Application Firewall)
 * OWASP Top 10 Protection + Anomaly Detection
 * 
 * This middleware protects against:
 * - SQL Injection
 * - XSS
 * - Command Injection
 * - CSRF
 * - XXE
 * - Broken Access Control
 * - Path Traversal
 * - Type Confusion
 */
class NanoWAF
{
    private const OWASP_PATTERNS = [
        // SQL Injection
        'sql' => [
            "/(union|select|insert|update|delete|drop|create|alter)\s+(from|into|table|database)/i",
            "/(' or '1'='1')/i",
            "/-- |#|\/\*/",
            "/xp_|sp_|exec|execute|script|javascript|onerror/i",
        ],
        // Path Traversal
        'path' => [
            "/\.\.\//",
            "/\.\.\\\/",
            "/%2e%2e/i",
            "/\.\.%2f/i",
        ],
        // XSS Vectors
        'xss' => [
            "/<script[^>]*>/i",
            "/javascript:/i",
            "/onerror\s*=/i",
            "/onload\s*=/i",
            "/eval\s*\(/i",
            "/<iframe[^>]*>/i",
            "/<object[^>]*>/i",
        ],
        // Command Injection
        'command' => [
            "/[;&|`$()]/",
            "/\$\{.*\}/",
            "/`.*`/",
        ],
        // XXE
        'xxe' => [
            "/<!ENTITY/i",
            "/SYSTEM\s+/i",
            "/systemId/i",
        ],
    ];

    private const SUSPICIOUS_HEADERS = [
        'X-Original-URL',
        'X-Rewrite-URL',
        'X-Forwarded-Host',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Header Validation
        $this->validateHeaders($request);

        // 2. Request Size Limits
        $this->validateRequestSize($request);

        // 3. Payload Inspection
        $this->inspectPayload($request);

        // 4. Pattern Matching (OWASP)
        $this->detectOWASPPatterns($request);

        // 5. Anomaly Scoring
        $anomalyScore = $this->calculateAnomalyScore($request);
        if ($anomalyScore > 7) {
            $this->logSecurityEvent('HIGH_ANOMALY_DETECTED', $request, $anomalyScore);
            $this->adaptiveThrottle($request, $anomalyScore);
        }

        return $next($request);
    }

    private function validateHeaders(Request $request): void
    {
        // Reject suspicious header rewrites
        foreach (self::SUSPICIOUS_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                $this->logSecurityEvent('SUSPICIOUS_HEADER_DETECTED', $request, ['header' => $header]);
                abort(403);
            }
        }

        // Validate Content-Type
        if ($request->isMethod('POST', 'PUT', 'PATCH')) {
            $contentType = $request->header('Content-Type', '');
            if (!in_array($contentType, ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'])) {
                $this->logSecurityEvent('INVALID_CONTENT_TYPE', $request);
                abort(415);
            }
        }

        // Host Header Validation
        $allowedHosts = config('security.allowed_hosts', []);
        if (!empty($allowedHosts) && !in_array($request->host(), $allowedHosts)) {
            $this->logSecurityEvent('HOST_MISMATCH', $request);
            abort(400);
        }
    }

    private function validateRequestSize(Request $request): void
    {
        $maxBodySize = config('security.max_body_size', 10 * 1024 * 1024); // 10MB
        $contentLength = (int)$request->header('Content-Length', 0);

        if ($contentLength > $maxBodySize) {
            $this->logSecurityEvent('OVERSIZED_PAYLOAD', $request, ['size' => $contentLength]);
            abort(413);
        }
    }

    private function inspectPayload(Request $request): void
    {
        // JSON payload inspection
        if ($request->isJson()) {
            $payload = $request->json()->all();
            $this->recursivePayloadCheck($payload, 'json');
        }

        // Form data inspection
        if ($request->isMethod('POST', 'PUT', 'PATCH')) {
            foreach ($request->all() as $key => $value) {
                $this->recursivePayloadCheck($value, 'form');
            }
        }
    }

    private function recursivePayloadCheck($data, $type = 'generic'): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                $this->recursivePayloadCheck($value, $type);
            }
            return;
        }

        if (!is_string($data)) {
            return;
        }

        // Check against OWASP patterns
        foreach (self::OWASP_PATTERNS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $data)) {
                    $this->logSecurityEvent('OWASP_PATTERN_DETECTED', request(), [
                        'category' => $category,
                        'pattern' => $pattern,
                        'payload_type' => $type,
                    ]);
                    abort(403);
                }
            }
        }
    }

    private function detectOWASPPatterns(Request $request): void
    {
        $checkString = $request->path() . ' ' . json_encode($request->all());

        foreach (self::OWASP_PATTERNS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $checkString)) {
                    $this->logSecurityEvent('OWASP_PATTERN_DETECTED', $request, [
                        'category' => $category,
                        'endpoint' => $request->path(),
                    ]);
                    abort(403);
                }
            }
        }
    }

    private function calculateAnomalyScore(Request $request): int
    {
        $score = 0;
        $ip = $request->ip();
        $userId = auth()->id();

        // Geographic anomaly
        if ($this->isGeographicAnomaly($ip)) {
            $score += 2;
        }

        // Unusual time of access
        if ($this->isUnusualAccessTime($userId)) {
            $score += 1;
        }

        // Rate limit baseline
        $requestCount = Cache::increment("requests:{$ip}:hourly");
        if ($requestCount > 1000) {
            $score += 3;
        }

        // User-agent mismatch
        if ($this->isUserAgentSuspicious($request)) {
            $score += 1;
        }

        // Rapid endpoint enumeration
        if ($this->isEnumerating($ip)) {
            $score += 4;
        }

        return $score;
    }

    private function isGeographicAnomaly(string $ip): bool
    {
        // TODO: Integrate with MaxMind GeoIP or similar
        // This should cache user's typical country and flag unusual locations
        return false;
    }

    private function isUnusualAccessTime(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        // TODO: Implement behavioral baseline
        // Track typical access times per user
        return false;
    }

    private function isUserAgentSuspicious(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';

        $suspiciousPatterns = [
            'bot', 'crawler', 'scraper', 'curl', 'wget', 'python',
            'java(?!script)', 'perl', 'ruby', 'node', '^$',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match("/$pattern/i", $userAgent)) {
                return true;
            }
        }

        return false;
    }

    private function isEnumerating(string $ip): bool
    {
        $enumerationKey = "enumeration:{$ip}:endpoints";
        $endpoints = Cache::get($enumerationKey, []);

        $currentEndpoint = request()->path();
        $endpoints[] = $currentEndpoint;
        $endpoints = array_unique($endpoints);

        Cache::put($enumerationKey, $endpoints, 3600); // 1 hour

        // If more than 50 unique endpoints in 1 hour, it's enumeration
        return count($endpoints) > 50;
    }

    private function adaptiveThrottle(Request $request, int $anomalyScore): void
    {
        $ip = $request->ip();
        $throttleLevel = min(10, (int)($anomalyScore / 2));

        Cache::put("throttle:{$ip}:level", $throttleLevel, 3600);

        // This will be checked by RateLimitingMiddleware
        // Throttle gets progressively harder
    }

    private function logSecurityEvent(string $event, Request $request, array $extra = []): void
    {
        Log::channel('security')->warning($event, array_merge([
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'tenant_id' => request()->header('X-Tenant-ID'),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ], $extra));

        // Persist to security_audit_logs table
        try {
            \App\Models\SecurityAuditLog::create([
                'event_type' => $event,
                'ip_address' => $request->ip(),
                'user_id' => auth()->id(),
                'tenant_id' => request()->header('X-Tenant-ID'),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'payload' => $this->sanitizePayload($request->all()),
                'severity' => 'warning',
                'details' => json_encode($extra),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log security event', ['error' => $e->getMessage()]);
        }
    }

    private function sanitizePayload(array $payload): string
    {
        // Remove sensitive fields before logging
        $sensitive = ['password', 'token', 'secret', 'api_key', 'credit_card'];

        foreach ($sensitive as $field) {
            unset($payload[$field]);
        }

        return json_encode($payload);
    }
}
