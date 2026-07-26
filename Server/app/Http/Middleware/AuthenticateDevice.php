<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateDevice
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (! is_string($token) || $token === '') {
            return ApiResponse::error('UNAUTHENTICATED', 'Device token required.', status: 401);
        }

        $device = Device::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if ($device === null) {
            return ApiResponse::error('UNAUTHENTICATED', 'Invalid device token.', status: 401);
        }

        if ($device->isRetired()) {
            return ApiResponse::error('FORBIDDEN', 'Device is retired.', status: 403);
        }

        $request->attributes->set('device', $device);
        $device->forceFill(['last_seen_at' => now()])->save();

        return $next($request);
    }
}
