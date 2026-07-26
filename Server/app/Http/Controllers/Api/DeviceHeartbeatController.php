<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use App\Services\HardwareRegistryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceHeartbeatController
{
    public function __invoke(Request $request, Device $device, HardwareRegistryService $hardware): JsonResponse
    {
        /** @var Device $caller */
        $caller = $request->attributes->get('device');

        if ($caller->id !== $device->id) {
            return ApiResponse::error('FORBIDDEN', 'Token does not match device.', status: 403);
        }

        $device = $hardware->recordHeartbeat($device);

        return ApiResponse::ok([
            'device_id' => $device->id,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'status' => $device->status->value,
        ]);
    }
}
