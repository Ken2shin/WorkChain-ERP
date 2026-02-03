<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nanodefense Adaptive Rate Limiting
 * 
 * Features:
 * - Per-IP, per-user, per-tenant limits
 * - Adaptive thresholds (hardens after attempts)
 * - Progressive backoff (not hard blocking)
 * - Distributed rate limiting
 * - Auto-recovery for legitimate traffic
 */
class NanoRateLimiting
{
    // Base limits (per minute)
    private const BASE_LIMITS = [
        'unauthenticated' => 60,
        'authenticated' => 300,
        'admin' => 1000,
    ];

    // Backoff multiplier when threshold exceeded
    private const BACKOFF_MULTIPLIER = 0.5;
    private const MAX_BACKOFF_LEVEL = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $identifier = $this->getIdentifier($request);
        $role = $this->getUserRole($request);

        // Check rate limit
        $limit = $this->getLimit($identifier, $role);
        $current = $this->getCurrentCount($identifier);

        if ($current >= $limit) {
            return $this->handleLimitExceeded($request, $identifier);
        }

        // Increment counter
        Cache::increment($identifier . ':count');

        // Add response headers
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $limit - $current - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addMinute()->timestamp);

        return $response;
    }

    private function getIdentifier(Request $request): string
    {
        $ip = $request->ip();
        $userId = auth()->id();
        $tenantId = $request->header('X-Tenant-ID', 'default');

        if ($userId) {
            return "ratelimit:{$tenantId}:user:{$userId}";
        }

        return "ratelimit:{$tenantId}:ip:{$ip}";
    }

    private function getUserRole(Request $request): string
    {
        if (!auth()->check()) {
            return 'unauthenticated';
        }

        $user = auth()->user();

        if ($user->is_admin) {
            return 'admin';
        }

        return 'authenticated';
    }

    private function getLimit(string $identifier, string $role): int
    {
        // Get base limit
        $baseLimit = self::BASE_LIMITS[$role] ?? 60;

        // Check if there's an adaptive backoff level
        $backoffLevel = Cache::get($identifier . ':backoff', 0);

        // Apply progressive backoff
        $limit = (int)($baseLimit * (1 - ($backoffLevel * self::BACKOFF_MULTIPLIER / 10)));

        return max(1, $limit); // Minimum 1 request per minute
    }

    private function getCurrentCount(string $identifier): int
    {
        return (int)Cache::get($identifier . ':count', 0);
    }

    private function handleLimitExceeded(Request $request, string $identifier): Response
    {
        // Increment backoff level
        $backoffLevel = Cache::get($identifier . ':backoff', 0);
        $backoffLevel = min(self::MAX_BACKOFF_LEVEL, $backoffLevel + 1);

        Cache::put($identifier . ':backoff', $backoffLevel, 3600); // 1 hour

        // Log the event
        Log::channel('security')->notice('RATE_LIMIT_EXCEEDED', [
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'identifier' => $identifier,
            'backoff_level' => $backoffLevel,
            'timestamp' => now(),
        ]);

        // Calculate retry-after
        $retryAfter = 60 * ($backoffLevel + 1); // Exponential backoff

        // Introduce randomness to prevent synchronized attacks
        $randomness = rand(0, $retryAfter * 0.2);
        $retryAfter += $randomness;

        return response()->json([
            'message' => 'Too many requests',
        ], 429)
            ->header('Retry-After', $retryAfter)
            ->header('X-RateLimit-Backoff-Level', $backoffLevel);
    }
}
