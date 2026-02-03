<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdaptiveRateLimiting
{
    private const DEFAULT_LIMIT = 60;
    private const STRICT_LIMIT = 10;
    private const ANOMALY_THRESHOLD = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    private const CACHE_PREFIX = 'rate_limit:';

    public function handle(Request $request, Closure $next)
    {
        $identifier = $this->getIdentifier($request);
        $isAnomaly = $this->detectAnomalies($request, $identifier);

        if ($this->isLocked($identifier)) {
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => Cache::get(self::CACHE_PREFIX . $identifier . ':lockout')
            ], 429);
        }

        $limit = $isAnomaly ? self::STRICT_LIMIT : self::DEFAULT_LIMIT;
        $currentCount = $this->incrementCounter($identifier, $limit);

        if ($currentCount > $limit) {
            $this->triggerLockout($identifier, $isAnomaly);
            
            Log::warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'anomaly' => $isAnomaly
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded'
            ], 429);
        }

        $response = $next($request);

        $response->header('X-RateLimit-Limit', $limit);
        $response->header('X-RateLimit-Remaining', max(0, $limit - $currentCount));

        return $response;
    }

    private function getIdentifier(Request $request): string
    {
        $user = $request->user();
        
        if ($user) {
            return 'user:' . $user->id;
        }

        return 'ip:' . $request->ip();
    }

    private function detectAnomalies(Request $request, string $identifier): bool
    {
        $endpoint = $request->path();
        $method = $request->method();
        $key = self::CACHE_PREFIX . $identifier . ':anomalies:' . hash('sha256', $endpoint . $method);
        
        // Detectar intentos fallidos repetidos
        $failedAttempts = Cache::get($key, 0);
        
        if ($request->getMethod() === 'POST' || $request->getMethod() === 'DELETE') {
            $failedAttempts++;
            Cache::put($key, $failedAttempts, 3600);
            
            if ($failedAttempts > self::ANOMALY_THRESHOLD) {
                return true;
            }
        }

        // Detectar cambios rápidos de endpoint
        $recentEndpoints = Cache::get(self::CACHE_PREFIX . $identifier . ':endpoints', []);
        $recentEndpoints[] = $endpoint;
        $recentEndpoints = array_slice($recentEndpoints, -10);
        
        $uniqueEndpoints = count(array_unique($recentEndpoints));
        if ($uniqueEndpoints > 7 && count($recentEndpoints) === 10) {
            return true;
        }

        Cache::put(self::CACHE_PREFIX . $identifier . ':endpoints', $recentEndpoints, 300);

        return false;
    }

    private function incrementCounter(string $identifier, int $limit): int
    {
        $key = self::CACHE_PREFIX . $identifier;
        $current = Cache::get($key, 0);
        $current++;
        
        Cache::put($key, $current, 60);
        
        return $current;
    }

    private function isLocked(string $identifier): bool
    {
        return Cache::has(self::CACHE_PREFIX . $identifier . ':locked');
    }

    private function triggerLockout(string $identifier, bool $isAnomaly): void
    {
        $duration = $isAnomaly ? self::LOCKOUT_DURATION * 2 : self::LOCKOUT_DURATION;
        Cache::put(self::CACHE_PREFIX . $identifier . ':locked', true, $duration);
        Cache::put(self::CACHE_PREFIX . $identifier . ':lockout', $duration, $duration);
    }
}
