<?php

namespace App\Http\Controllers\Api;

// 🔥 CAMBIO RADICAL (MANTENIDO):
// Heredamos directamente del núcleo de Laravel para evitar errores de archivos corruptos.
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * 🛡️ MÉTODO PRIVADO DE SEGURIDAD
     * Centraliza la creación de respuestas para inyectar headers de seguridad
     * y asegurar la codificación correcta de caracteres.
     */
    private function secureResponse(array $data, int $code): JsonResponse
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
            ->header('Content-Type', 'application/json')
            ->header('X-Content-Type-Options', 'nosniff') // Evita MIME sniffing
            ->header('X-Frame-Options', 'DENY')           // Evita Clickjacking
            ->header('X-XSS-Protection', '1; mode=block'); // Protección XSS adicional
    }

    /**
     * Respuesta de éxito estandarizada.
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $this->secureResponse([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Respuesta de error estandarizada.
     */
    protected function error(string $message = 'Error', $errors = null, int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->secureResponse($response, $code);
    }

    // --- Helpers de estado HTTP ---

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, null, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, null, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, null, 403);
    }

    /**
     * Validador seguro.
     */
    protected function validateDataOrFail(array $data, array $rules): JsonResponse|array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        return $validator->validated();
    }
}