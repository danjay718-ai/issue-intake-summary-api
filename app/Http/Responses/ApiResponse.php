<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Provides the shared success response envelope used by API controllers.
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
            'data' => $data,
        ], $status);
    }
}
