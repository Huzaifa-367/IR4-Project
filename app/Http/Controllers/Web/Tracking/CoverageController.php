<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Enums\DeviceType;
use App\Http\Controllers\Web\BaseController;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CoverageController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view-tracking'), 403);

        $coverage = Device::query()
            ->where('device_type', DeviceType::RfidReader)
            ->with(['currentZoneBinding.zone:id,uuid,name,zone_type,latitude,longitude,radius_meters,color'])
            ->orderBy('name')
            ->get()
            ->map(function (Device $device): array {
                $zone = $device->currentZoneBinding?->zone;

                return [
                    'device_id' => $device->id,
                    'device_uuid' => $device->uuid,
                    'device_name' => $device->name,
                    'reference' => $device->reference,
                    'zone' => $zone === null ? null : [
                        'id' => $zone->id,
                        'uuid' => $zone->uuid,
                        'name' => $zone->name,
                        'zone_type' => $zone->zone_type->value,
                        'latitude' => $zone->latitude,
                        'longitude' => $zone->longitude,
                        'radius_meters' => $zone->radius_meters,
                        'color' => $zone->color,
                    ],
                ];
            });

        return response()->json(['data' => $coverage]);
    }
}
