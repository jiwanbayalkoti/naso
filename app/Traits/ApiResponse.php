<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

trait ApiResponse
{
    /**
     * Return a successful JSON response.
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(
        string $message = 'Error',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error JSON response.
     */
    protected function validationError(
        ValidationException|string|array $exception,
        int $statusCode = 422
    ): JsonResponse {
        if ($exception instanceof ValidationException) {
            return $this->error(
                $exception->getMessage(),
                $statusCode,
                $exception->errors()
            );
        }

        return $this->error(
            is_string($exception) ? $exception : 'Validation failed.',
            $statusCode,
            is_array($exception) ? $exception : null
        );
    }
}
