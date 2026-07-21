<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\DeviceType;
use App\Enums\ZoneType;
use App\Http\Controllers\Web\BaseController;
use App\Models\Device;
use App\Models\Zone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RepositioningController extends BaseController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view-zones'), 403);

        $readers = Device::query()
            ->where('device_type', DeviceType::RfidReader)
            ->with([
                'asset:id,name,current_location_label,is_mobile',
                'currentZoneBinding.zone:id,name,zone_type',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Device $device): array {
                $binding = $device->currentZoneBinding;
                $zone = $binding?->zone;

                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'reference' => $device->reference,
                    'asset' => $device->asset === null ? null : [
                        'id' => $device->asset->id,
                        'name' => $device->asset->name,
                        'current_location_label' => $device->asset->current_location_label,
                        'is_mobile' => $device->asset->is_mobile,
                    ],
                    'current_zone' => $zone === null ? null : [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'zone_type' => $zone->zone_type->value,
                        'is_gate' => $zone->zone_type === ZoneType::Gate,
                    ],
                    'bound_from' => $binding?->bound_from?->toIso8601String(),
                ];
            });

        return Inertia::render('settings/repositioning', [
            'readers' => $readers,
            'zones' => Zone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'zone_type'])
                ->map(fn (Zone $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'zone_type' => $zone->zone_type->value,
                    'zone_type_label' => $zone->zone_type->label(),
                ]),
            'zoneTypes' => collect(ZoneType::cases())->map(fn (ZoneType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'flash' => [
                'gate_warning' => $request->session()->get('flash.gate_warning'),
                'success' => $request->session()->get('flash.success'),
            ],
        ]);
    }
}
