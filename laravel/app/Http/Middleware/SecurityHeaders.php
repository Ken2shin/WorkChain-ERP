<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Prevenir clickjacking
        $response->header('X-Frame-Options', 'DENY');
        
        // Prevenir MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');
        
        // Habilitar XSS protection
        $response->header('X-XSS-Protection', '1; mode=block');
        
        // Content Security Policy
        $response->header('Content-Security-Policy', 
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none'"
        );
        
        // HSTS (HTTP Strict Transport Security)
        if (config('app.force_https')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Prevenir caching de contenido sensible
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }
}
