<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\MobileReturnEquipmentRequest;
use App\Http\Requests\Web\Equipment\CheckoutEquipmentRequest;
use App\Models\Equipment;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\EquipmentCheckoutService;
use App\Services\EquipmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MobileEquipmentController extends Controller
{
    public function scan(
        Request $request,
        string $qrToken,
        EquipmentService $equipmentService,
    ): JsonResponse {
        $equipment = Equipment::query()
            ->with(['openCheckout.worker', 'openCheckout.zone', 'maintenanceSchedules'])
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        $this->authorize('view', $equipment);

        $user = $request->user();
        $canSeeIdentity = $user?->can('view-worker-identity') ?? false;
        $canCheckout = $user?->can('checkout', $equipment) ?? false;

        return ApiResponse::ok([
            'equipment' => $equipmentService->toArray($equipment, includeRelations: true, canSeeIdentity: $canSeeIdentity),
            'workers' => Worker::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name'])
                ->map(fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'uuid' => $worker->uuid,
                    'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                ])
                ->values()
                ->all(),
            'zones' => Zone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name'])
                ->map(fn (Zone $zone): array => [
                    'id' => $zone->id,
                    'uuid' => $zone->uuid,
                    'name' => $zone->name,
                ])
                ->values()
                ->all(),
            'can_checkout' => $canCheckout,
        ]);
    }

    public function checkout(
        CheckoutEquipmentRequest $request,
        Equipment $equipment,
        EquipmentCheckoutService $checkouts,
        EquipmentService $equipmentService,
    ): JsonResponse {
        $checkout = $checkouts->checkout($equipment, $request->validated(), $request->user());
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;
        $equipment->refresh()->load(['openCheckout.worker', 'openCheckout.zone']);

        return ApiResponse::ok([
            'checkout' => $checkouts->checkoutPayload($checkout, $canSeeIdentity),
            'equipment' => $equipmentService->toArray($equipment, includeRelations: false, canSeeIdentity: $canSeeIdentity),
        ], 201);
    }

    public function returnItem(
        MobileReturnEquipmentRequest $request,
        Equipment $equipment,
        EquipmentCheckoutService $checkouts,
        EquipmentService $equipmentService,
    ): JsonResponse {
        $open = $equipment->openCheckout;
        if ($open === null) {
            throw new HttpException(409, 'Equipment has no open checkout to return.');
        }

        $checkout = $checkouts->returnCheckout($open, $request->validated(), $request->user());
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;
        $equipment->refresh()->load(['openCheckout.worker', 'openCheckout.zone']);

        return ApiResponse::ok([
            'checkout' => $checkouts->checkoutPayload($checkout, $canSeeIdentity),
            'equipment' => $equipmentService->toArray($equipment, includeRelations: false, canSeeIdentity: $canSeeIdentity),
        ]);
    }
}
