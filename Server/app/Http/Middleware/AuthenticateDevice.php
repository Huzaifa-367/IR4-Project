<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Services\HardwareRegistryService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateDevice
{
    public function __construct(
        private readonly HardwareRegistryService $hardware,
    ) {}

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

        // Keep last_seen + Online status aligned with health checks (DOC-05).
        $device = $this->hardware->touchPresence($device);
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
