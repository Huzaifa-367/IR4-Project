<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController extends BaseController
{
    public function heartbeat(Request $request): JsonResponse
    {
        $request->session()->put('last_activity_at', now()->getTimestamp());

        return response()->json([
            'data' => [
                'ok' => true,
                'last_activity_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
