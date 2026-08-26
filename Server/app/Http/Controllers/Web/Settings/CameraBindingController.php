<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\RebindCameraRequest;
use App\Models\Camera;
use App\Models\Zone;
use App\Services\Tracking\CameraZoneBindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

final class CameraBindingController extends BaseController
{
    public function store(
        RebindCameraRequest $request,
        Camera $camera,
        CameraZoneBindingService $bindings,
    ): RedirectResponse {
        $data = $request->validated();
        /** @var Zone $zone */
        $zone = Zone::query()->findOrFail($data['zone_id']);
        $effectiveAt = isset($data['effective_at'])
            ? Carbon::parse($data['effective_at'])
            : now();

        $bindings->bind(
            $camera,
            $zone,
            $effectiveAt,
            $request->user(),
            $data['note'] ?? null,
        );

        if (($data['asset_location_label'] ?? null) !== null && $camera->asset_id !== null) {
            $camera->asset?->forceFill([
                'current_location_label' => $data['asset_location_label'],
            ])->save();
        }

        return redirect()
            ->route('settings.repositioning')
            ->with('flash', [
                'success' => 'Camera rebound to zone.',
            ]);
    }
}
