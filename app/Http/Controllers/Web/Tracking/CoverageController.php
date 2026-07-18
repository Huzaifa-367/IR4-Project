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
            ->with(['currentZoneBinding.zone:id,name,zone_type,map_x,map_y,map_radius,color'])
            ->orderBy('name')
            ->get()
            ->map(function (Device $device): array {
                $zone = $device->currentZoneBinding?->zone;

                return [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'reference' => $device->reference,
                    'zone' => $zone === null ? null : [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'zone_type' => $zone->zone_type->value,
                        'map_x' => $zone->map_x,
                        'map_y' => $zone->map_y,
                        'map_radius' => $zone->map_radius,
                        'color' => $zone->color,
                    ],
                ];
            });

        return response()->json(['data' => $coverage]);
    }
}
