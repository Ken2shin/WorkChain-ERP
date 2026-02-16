<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;
use Firebase\JWT\ExpiredException;

class JWTService
{
    private string $secret;
    private string $algorithm;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->algorithm = config('jwt.algorithm', 'HS256');

        if (empty($this->secret) || strlen($this->secret) < 32) {
            throw new RuntimeException(
                'JWT_SECRET is missing or insecure (minimum 32 characters required).'
            );
        }
    }

    /**
     * ACCESS TOKEN (15 min)
     */
    public function issueUserToken(
        int|string $userId,
        int|string $tenantId,
        array $customClaims = []
    ): string {
        $payload = array_merge($customClaims, [
            'sub'       => (string) $userId,
            'tenant_id' => (string) $tenantId,
            'jti'       => Str::uuid()->toString(),
            'type'      => 'access',
        ]);

        return $this->generateRawToken(
            $payload,
            config('jwt.access_expiration', 15)
        );
    }

    /**
     * REFRESH TOKEN (7 días)
     */
    public function issueRefreshToken(
        int|string $userId,
        int|string $tenantId
    ): string {
        return $this->generateRawToken([
            'sub'       => (string) $userId,
            'tenant_id' => (string) $tenantId,
            'type'      => 'refresh',
            'jti'       => Str::uuid()->toString(),
        ], config('jwt.refresh_expiration', 10080));
    }

    /**
     * Verificación segura del token
     */
    public function verifyToken(string $token): array
    {
        try {
            JWT::$leeway = 10;

            $decoded = JWT::decode(
                $token,
                new Key($this->secret, $this->algorithm)
            );

            $payload = (array) $decoded;

            if (empty($payload['tenant_id'])) {
                throw new UnexpectedValueException('Token missing tenant context.');
            }

            return $payload;

        } catch (ExpiredException) {
            throw new RuntimeException('Token expired.');
        } catch (\Throwable) {
            throw new RuntimeException('Invalid token.');
        }
    }

    /**
     * Token builder interno
     */
    private function generateRawToken(array $payload, int $expirationMinutes): string
    {
        $now = time();

        return JWT::encode(array_merge($payload, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($expirationMinutes * 60),
        ]), $this->secret, $this->algorithm);
    }

    /**
     * Extraer Bearer Token
     */
    public function extractTokenFromHeader(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        return preg_match('/Bearer\s+(\S+)/', $header, $m)
            ? $m[1]
            : null;
    }
}
