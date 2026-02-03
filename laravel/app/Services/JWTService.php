<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTService
{
    private string $secret;
    private string $algorithm = 'HS256';
    private int $expiration = 86400; // 24 hours

    public function __construct()
    {
        $this->secret = config('app.jwt_secret') ?? env('JWT_SECRET', 'secret');
    }

    public function generateToken(array $payload, int $expirationMinutes = null): string
    {
        $issuedAt = time();
        $expire = $issuedAt + (($expirationMinutes ?? 1440) * 60);

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expire;

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function validateTokenStructure(string $token): bool
    {
        $parts = explode('.', $token);
        return count($parts) === 3;
    }

    public function isTokenExpired(array $payload): bool
    {
        return $payload['exp'] < time();
    }

    public function generateRefreshToken(array $payload): string
    {
        // Refresh tokens have longer expiration (7 days)
        return $this->generateToken($payload, 10080);
    }

    public function extractTokenFromHeader(string $header): ?string
    {
        if (preg_match('/Bearer\s+(.*)/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
