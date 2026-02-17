<?php

namespace App\Http\Controllers\Api;

// Importamos el controlador base de Laravel correctamente
use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ApiController extends BaseController
{
    /**
     * Respuesta de Éxito Estándar
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
     * Respuesta de Error Estándar
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

    /**
     * Helpers rápidos para códigos HTTP comunes
     */
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
     * Validador seguro que devuelve JSON automáticamente si falla
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