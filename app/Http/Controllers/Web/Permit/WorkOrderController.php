<?php

namespace App\Http\Controllers\Web\Permit;

use App\Http\Controllers\Web\BaseController;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Services\PermitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class WorkOrderController extends BaseController
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->can('view-permits'), 403);

        $query = WorkOrder::query()->with(['zone:id,name']);

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['reference', 'status', 'created_at'],
            searchable: ['reference', 'description'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('workforce/work-orders/index', [
            'workOrders' => [
                'data' => $paginator->getCollection()->map(fn (WorkOrder $row): array => [
                    'id' => $row->id,
                    'reference' => $row->reference,
                    'description' => $row->description,
                    'status' => $row->status,
                    'zone' => $row->zone === null ? null : [
                        'id' => $row->zone->id,
                        'name' => $row->zone->name,
                    ],
                    'permits_count' => $row->permits()->count(),
                    'created_at' => $row->created_at?->toIso8601String(),
                ])->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'created_at',
                'direction' => $request->string('direction')->toString() ?: 'desc',
            ],
            'canCreate' => $request->user()?->can('request-permit') ?? false,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->can('request-permit'), 403);

        return Inertia::render('workforce/work-orders/create', [
            'zones' => Zone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('request-permit'), 403);

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64', Rule::unique('work_orders', 'reference')],
            'description' => ['nullable', 'string', 'max:5000'],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
        ]);

        $workOrder = WorkOrder::query()->create([
            'reference' => $validated['reference'],
            'description' => $validated['description'] ?? null,
            'zone_id' => $validated['zone_id'] ?? null,
            'status' => 'open',
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('flash', ['success' => 'Work order created.']);
    }

    public function show(Request $request, WorkOrder $workOrder, PermitService $permits): InertiaResponse
    {
        abort_unless($request->user()?->can('view-permits'), 403);

        $workOrder->load([
            'zone:id,name',
            'permits.type:id,code,name,colour_token',
            'permits.zone:id,name',
        ]);

        $clearance = $permits->workOrderClearance($workOrder);

        return Inertia::render('workforce/work-orders/show', [
            'workOrder' => [
                'id' => $workOrder->id,
                'reference' => $workOrder->reference,
                'description' => $workOrder->description,
                'status' => $workOrder->status,
                'zone' => $workOrder->zone === null ? null : [
                    'id' => $workOrder->zone->id,
                    'name' => $workOrder->zone->name,
                ],
                'created_at' => $workOrder->created_at?->toIso8601String(),
            ],
            'clearance' => $clearance,
            'canCreatePermit' => $request->user()?->can('request-permit') ?? false,
        ]);
    }
}
