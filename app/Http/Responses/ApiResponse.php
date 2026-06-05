<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Provides shared success and error response envelopes used by API controllers.
 */
trait ApiResponse
{
    /**
     * Build a consistent success response: { success, message, data }.
     */
    protected function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Build a consistent error response: { success, message }.
     */
    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
