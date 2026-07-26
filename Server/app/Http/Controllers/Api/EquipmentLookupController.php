<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use Illuminate\Http\Request;

/**
 * Authenticated scan lookup (DOC-13 §4.5) — distinct from public GET /e/{qr_token}.
 */
final class EquipmentLookupController extends Controller
{
    public function show(Request $request, string $qrToken): EquipmentResource
    {
        $equipment = Equipment::query()
            ->with(['openCheckout.worker', 'openCheckout.zone', 'maintenanceSchedules', 'documents'])
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        $this->authorize('view', $equipment);

        return new EquipmentResource($equipment);
    }
}
