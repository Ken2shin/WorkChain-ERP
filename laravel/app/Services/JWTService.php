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
    private string $algorithm = 'HS256'; // Para mayor seguridad, considera RS256 (Claves asimétricas)

    public function __construct()
    {
        // 1. SEGURIDAD: Cargar desde config(), nunca env().
        // Si la clave no existe o es débil, detenemos la ejecución inmediatamente.
        $this->secret = config('jwt.secret');

        if (empty($this->secret) || strlen($this->secret) < 32) {
            throw new RuntimeException('CRITICAL: JWT_SECRET is missing or too short (min 32 chars).');
        }
    }

    /**
     * Genera un token de acceso vinculado estrictamente a un Usuario y una Organización.
     * Esto soluciona tu problema de filtrado.
     */
    public function issueUserToken(int|string $userId, string $tenantId, array $customClaims = []): string
    {
        // Forzamos la estructura del Payload para garantizar el aislamiento
        $payload = array_merge($customClaims, [
            'sub'       => (string) $userId,   // Subject (Quién es)
            'tenant_id' => $tenantId,          // Context (Dónde está) <--- CRÍTICO
            'jti'       => Str::uuid()->toString(), // Unique Token ID (Previene replay attacks)
        ]);

        // 15 minutos de vida para Access Tokens (Seguridad Brutal requiere rotación rápida)
        return $this->generateRawToken($payload, 15);
    }

    /**
     * Genera un Refresh Token de larga duración.
     */
    public function issueRefreshToken(int|string $userId, string $tenantId): string
    {
        $payload = [
            'sub'       => (string) $userId,
            'tenant_id' => $tenantId,
            'type'      => 'refresh', // Marca explícita para no confundirlo con access token
            'jti'       => Str::uuid()->toString(),
        ];

        return $this->generateRawToken($payload, 10080); // 7 días
    }

    /**
     * Verifica y decodifica el token. Lanza excepciones específicas.
     */
    public function verifyToken(string $token): array
    {
        try {
            // Añadimos un "leeway" de 10 segundos por si hay desajuste de reloj entre servidores
            JWT::$leeway = 10; 
            
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            $payload = (array) $decoded;

            // Validación Estructural: Si no tiene tenant_id, el token es basura.
            if (!isset($payload['tenant_id']) || empty($payload['tenant_id'])) {
                throw new UnexpectedValueException('Token missing Organization Context (tenant_id).');
            }

            return $payload;

        } catch (ExpiredException $e) {
            // Puedes loguear esto si quieres auditoría de sesiones expiradas
            throw new \App\Exceptions\Auth\TokenExpiredException('Session expired.');
        } catch (\Exception $e) {
            throw new \App\Exceptions\Auth\InvalidTokenException('Invalid token signature or structure.');
        }
    }

    /**
     * Generación interna de bajo nivel.
     */
    private function generateRawToken(array $payload, int $expirationMinutes): string
    {
        $issuedAt = time();
        $expire = $issuedAt + ($expirationMinutes * 60);

        $payload['iat'] = $issuedAt; // Issued At
        $payload['nbf'] = $issuedAt; // Not Before (Válido desde ya)
        $payload['exp'] = $expire;   // Expiration

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Extrae el token del header Authorization con validación robusta.
     */
    public function extractTokenFromHeader(?string $header): ?string
    {
        if (empty($header)) {
            return null;
        }

        if (preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}