<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function accepted(array $data): JsonResponse
    {
        return response()->json($data, 202);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function error(
        string $code,
        string $message,
        ?array $details = null,
        int $status = 400,
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }
}
