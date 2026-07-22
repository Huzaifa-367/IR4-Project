<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\DeviceType;
use App\Enums\ZoneType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\RebindReaderRequest;
use App\Models\Device;
use App\Models\ReaderZoneBinding;
use App\Models\Zone;
use App\Services\ReaderBindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class ReaderBindingController extends BaseController
{
    public function store(
        RebindReaderRequest $request,
        Device $device,
        ReaderBindingService $bindings,
    ): RedirectResponse {
        $data = $request->validated();
        /** @var Zone $zone */
        $zone = Zone::query()->findOrFail($data['zone_id']);
        $effectiveAt = isset($data['effective_at'])
            ? Carbon::parse($data['effective_at'])
            : now();

        $result = $bindings->bind(
            $device,
            $zone,
            $effectiveAt,
            $request->user(),
            $data['note'] ?? null,
        );

        if (($data['asset_location_label'] ?? null) !== null && $device->asset_id !== null) {
            $device->asset?->forceFill([
                'current_location_label' => $data['asset_location_label'],
            ])->save();
        }

        return redirect()
            ->route('settings.repositioning')
            ->with('flash', [
                'success' => 'Reader rebound.',
                'gate_warning' => $result['gate_warning'],
            ]);
    }

    public function history(Device $device): Response
    {
        abort_unless(request()->user()?->can('update-zones'), 403);

        $history = ReaderZoneBinding::query()
            ->where('device_id', $device->id)
            ->with('zone:id,uuid,name,zone_type')
            ->orderByDesc('bound_from')
            ->get()
            ->map(fn (ReaderZoneBinding $binding): array => [
                'id' => $binding->id,
                'zone_id' => $binding->zone_id,
                'zone_uuid' => $binding->zone?->uuid,
                'zone_name' => $binding->zone?->name,
                'zone_type' => $binding->zone?->zone_type->value,
                'bound_from' => $binding->bound_from?->toIso8601String(),
                'bound_until' => $binding->bound_until?->toIso8601String(),
                'note' => $binding->note,
            ]);
        return Inertia::render('settings/readers/bindings', [
            'device' => [
                'id' => $device->id,
                'uuid' => $device->uuid,
                'name' => $device->name,
                'reference' => $device->reference,
            ],
            'bindings' => $history,
        ]);
    }
}
