<?php

namespace App\Http\Controllers\Api;

// 🔥 CAMBIO RADICAL:
// Saltamos el archivo "App\Http\Controllers\Controller" que da problemas
// y vamos directo al núcleo de Laravel.
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ApiController extends BaseController
{
    // Agregamos los Traits manualmente porque nos saltamos el controlador intermedio
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Respuesta de éxito estandarizada.
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
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

        return response()->json($response, $code);
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