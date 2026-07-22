<?php

namespace App\Http\Controllers\Web\Reports;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Reports\StoreVehicleViolationRequest;
use App\Models\Camera;
use App\Models\VehicleViolation;
use App\Services\VehicleViolationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class VehicleViolationController extends BaseController
{
    public function index(Request $request, VehicleViolationService $service): InertiaResponse
    {
        $this->authorize('viewAny', VehicleViolation::class);

        $query = VehicleViolation::query()->with(['camera', 'logger']);

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['observed_at', 'violation_type', 'created_at'],
            searchable: ['vehicle_description', 'violation_type', 'description', 'action_taken'],
            defaultSort: 'observed_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate(20)->withQueryString();

        return Inertia::render('reports/vehicle-violations/index', [
            'violations' => [
                'data' => collect($paginator->items())->map(fn (VehicleViolation $v) => $service->toArray($v))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
            'violationTypes' => VehicleViolationService::violationTypes(),
            'cameras' => Camera::query()->orderBy('name')->get(['id', 'uuid', 'name', 'reference']),
            'canCreate' => $request->user()?->can('create-vehicle-violations') ?? false,
        ]);
    }

    public function store(StoreVehicleViolationRequest $request, VehicleViolationService $service): RedirectResponse
    {
        $this->authorize('create', VehicleViolation::class);
        $service->create($request->validated(), $request->user());

        return back()->with('success', 'Vehicle violation logged.');
    }

    public function destroy(VehicleViolation $vehicleViolation): RedirectResponse
    {
        $this->authorize('delete', $vehicleViolation);
        $vehicleViolation->delete();

        return back()->with('success', 'Vehicle violation removed.');
    }
}
