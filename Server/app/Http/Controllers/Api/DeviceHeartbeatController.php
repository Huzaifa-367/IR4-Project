<?php

namespace App\Http\Controllers\Api;

use App\Enums\HardwareStatus;
use App\Http\Requests\Api\DeviceHeartbeatRequest;
use App\Models\Device;
use App\Services\Hardware\HardwareRegistryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DeviceHeartbeatController
{
    public function __invoke(
        DeviceHeartbeatRequest $request,
        Device $device,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $caller */
        $caller = $request->attributes->get('device');

        if ($caller->id !== $device->id) {
            return ApiResponse::error('FORBIDDEN', 'Token does not match device.', status: 403);
        }

        $validated = $request->validated();
        $status = isset($validated['status'])
            ? HardwareStatus::from((string) $validated['status'])
            : null;
        /** @var array<string, mixed>|null $meta */
        $meta = $validated['meta'] ?? null;

        $device = $hardware->recordHeartbeat($device, $status, $meta);

        return ApiResponse::ok([
            'device_id' => $device->id,
            'device_uuid' => $device->uuid,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'status' => $device->status->value,
        ]);
    }
}
