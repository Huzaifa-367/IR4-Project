<?php

namespace App\Http\Controllers\Web\Equipment;

use App\Enums\EquipmentStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Equipment\CheckoutEquipmentRequest;
use App\Http\Requests\Web\Equipment\ImportEquipmentRequest;
use App\Http\Requests\Web\Equipment\PrintEquipmentLabelsRequest;
use App\Http\Requests\Web\Equipment\ReturnEquipmentCheckoutRequest;
use App\Http\Requests\Web\Equipment\StoreEquipmentDocumentRequest;
use App\Http\Requests\Web\Equipment\StoreEquipmentInspectionRequest;
use App\Http\Requests\Web\Equipment\StoreEquipmentMaintenanceRequest;
use App\Http\Requests\Web\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Web\Equipment\SyncEquipmentSchedulesRequest;
use App\Http\Requests\Web\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Jobs\ImportEquipmentJob;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\EquipmentDocument;
use App\Models\EquipmentImport;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\EquipmentCheckoutService;
use App\Services\EquipmentLabelService;
use App\Services\EquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EquipmentController extends BaseController
{
    public function index(Request $request, EquipmentService $equipmentService): InertiaResponse
    {
        $this->authorize('viewAny', Equipment::class);

        $query = Equipment::query()->with(['openCheckout.worker', 'openCheckout.zone', 'maintenanceSchedules']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('equipment_type')) {
            $query->where('equipment_type', $request->string('equipment_type')->toString());
        }

        if ($request->boolean('overdue')) {
            $today = now()->toDateString();
            $query->where(function ($q) use ($today): void {
                $q->whereDate('next_inspection_due', '<', $today)
                    ->orWhereDate('next_service_due', '<', $today);
            });
        }

        if ($request->filled('checkout_state')) {
            $state = $request->string('checkout_state')->toString();
            if ($state === 'checked_out' || $state === 'overdue_return') {
                $query->whereHas('openCheckout', function ($q) use ($state): void {
                    if ($state === 'overdue_return') {
                        $q->whereNotNull('expected_return_at')->where('expected_return_at', '<', now());
                    }
                });
            } elseif ($state === 'available') {
                $query->whereDoesntHave('openCheckout');
            }
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['equipment_code', 'name', 'equipment_type', 'status', 'next_inspection_due', 'next_service_due', 'created_at'],
            searchable: ['equipment_code', 'name', 'equipment_type', 'location_label'],
            defaultSort: 'equipment_code',
            defaultDirection: 'asc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('equipment/index', [
            'equipment' => [
                'data' => $paginator->getCollection()
                    ->map(fn (Equipment $item): array => $equipmentService->toArray($item, false, $canSeeIdentity))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'equipment_type' => $request->string('equipment_type')->toString(),
                'overdue' => $request->has('overdue') ? $request->boolean('overdue') : null,
                'checkout_state' => $request->string('checkout_state')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'equipment_code',
                'direction' => $request->string('direction')->toString() ?: 'asc',
            ],
            'statusOptions' => collect(EquipmentStatus::cases())->map(fn (EquipmentStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
            'typeOptions' => Equipment::query()->select('equipment_type')->distinct()->orderBy('equipment_type')->pluck('equipment_type')->all(),
            'workers' => Worker::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name'])->map(
                fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'uuid' => $worker->uuid,
                    'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                ],
            )->values()->all(),
            'zones' => Zone::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name']),
            'canManage' => ($request->user()?->can('create-equipment') || $request->user()?->can('update-equipment') || $request->user()?->can('delete-equipment')) ?? false,
        ]);
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Equipment::class);

        return redirect()->route('equipment.index');
    }

    public function store(StoreEquipmentRequest $request, EquipmentService $equipmentService): RedirectResponse
    {
        $equipment = $equipmentService->create($request->validated());

        return redirect()
            ->route('equipment.show', $equipment)
            ->with('flash', ['success' => 'Equipment created.']);
    }

    public function show(Request $request, Equipment $equipment, EquipmentService $equipmentService): InertiaResponse
    {
        $this->authorize('view', $equipment);

        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('equipment/show', [
            'equipment' => $equipmentService->toArray($equipment, true, $canSeeIdentity),
            'workers' => Worker::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name'])->map(
                fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'uuid' => $worker->uuid,
                    'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                ],
            )->values()->all(),
            'zones' => Zone::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name']),
            'canManage' => ($request->user()?->can('create-equipment') || $request->user()?->can('update-equipment') || $request->user()?->can('delete-equipment')) ?? false,
        ]);
    }

    public function update(
        UpdateEquipmentRequest $request,
        Equipment $equipment,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $equipmentService->update($equipment, $request->validated());

        return back()->with('flash', ['success' => 'Equipment updated.']);
    }

    public function retire(Equipment $equipment, EquipmentService $equipmentService): RedirectResponse
    {
        $this->authorize('update', $equipment);
        $equipmentService->retire($equipment);

        return back()->with('flash', ['success' => 'Equipment retired.']);
    }

    public function destroy(Equipment $equipment, EquipmentService $equipmentService): RedirectResponse
    {
        $this->authorize('delete', $equipment);
        $equipmentService->destroy($equipment);

        return redirect()
            ->route('equipment.index')
            ->with('flash', ['success' => 'Equipment deleted.']);
    }

    public function storeInspection(
        StoreEquipmentInspectionRequest $request,
        Equipment $equipment,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $equipmentService->addInspection($equipment, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Inspection recorded.']);
    }

    public function storeMaintenance(
        StoreEquipmentMaintenanceRequest $request,
        Equipment $equipment,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $equipmentService->addMaintenance($equipment, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Maintenance recorded.']);
    }

    public function syncSchedules(
        SyncEquipmentSchedulesRequest $request,
        Equipment $equipment,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $equipmentService->syncSchedules($equipment, $request->validated('schedules'));

        return back()->with('flash', ['success' => 'Schedules updated.']);
    }

    public function storeDocument(
        StoreEquipmentDocumentRequest $request,
        Equipment $equipment,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $equipmentService->addDocument($equipment, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Document uploaded.']);
    }

    public function destroyDocument(
        Equipment $equipment,
        EquipmentDocument $document,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $this->authorize('manage', $equipment);
        abort_unless($document->equipment_id === $equipment->id, 404);
        $equipmentService->deleteDocument($document);

        return back()->with('flash', ['success' => 'Document removed.']);
    }

    public function checkout(
        CheckoutEquipmentRequest $request,
        Equipment $equipment,
        EquipmentCheckoutService $checkouts,
    ): RedirectResponse {
        $checkouts->checkout($equipment, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Equipment checked out.']);
    }

    public function returnCheckout(
        ReturnEquipmentCheckoutRequest $request,
        EquipmentCheckout $checkout,
        EquipmentCheckoutService $checkouts,
    ): RedirectResponse {
        $checkouts->returnItem($checkout, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Equipment returned.']);
    }

    public function checkoutsIndex(Request $request, EquipmentService $equipmentService): InertiaResponse
    {
        $this->authorize('viewAny', Equipment::class);

        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;
        $openOnly = ! $request->has('open') || $request->boolean('open');

        $query = EquipmentCheckout::query()
            ->with(['equipment.openCheckout', 'worker', 'zone', 'checkedOutByUser', 'returnedToUser'])
            ->when($openOnly, fn ($q) => $q->whereNull('returned_at'))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $q->where(function ($inner) use ($search): void {
                    $inner->whereHas('equipment', function ($equipment) use ($search): void {
                        $equipment->where('equipment_code', 'like', $search)
                            ->orWhere('name', 'like', $search);
                    })->orWhereHas('worker', function ($worker) use ($search): void {
                        $worker->where('name', 'like', $search);
                    });
                });
            })
            ->latest('checked_out_at');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('equipment/checkouts/index', [
            'checkouts' => [
                'data' => $paginator->getCollection()->map(function (EquipmentCheckout $row) use ($canSeeIdentity, $equipmentService): array {
                    $payload = $equipmentService->checkoutToArray($row, $canSeeIdentity) ?? [];
                    $payload['equipment'] = $equipmentService->toArray($row->equipment, false, $canSeeIdentity);

                    return $payload;
                })->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'open' => $openOnly,
                'search' => $request->string('search')->toString(),
            ],
            'returnStatuses' => collect(ReturnStatus::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
            'canManage' => ($request->user()?->can('create-equipment') || $request->user()?->can('update-equipment') || $request->user()?->can('delete-equipment')) ?? false,
        ]);
    }

    public function byToken(string $qrToken): EquipmentResource
    {
        $equipment = Equipment::query()
            ->with(['openCheckout.worker', 'openCheckout.zone', 'maintenanceSchedules', 'documents'])
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        $this->authorize('view', $equipment);

        return new EquipmentResource($equipment);
    }

    public function qr(
        Request $request,
        Equipment $equipment,
        EquipmentLabelService $labels,
    ): Response {
        $this->authorize('view', $equipment);

        $format = $request->string('format')->toString() ?: 'png';
        $sizeMm = max(20, min(100, (int) $request->integer('size', 50)));

        $filename = $equipment->equipment_code;

        return match ($format) {
            'svg' => response($labels->svg($equipment, $sizeMm), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.svg"',
            ]),
            'zpl' => response($labels->zpl($equipment), 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.zpl"',
            ]),
            default => response($labels->png($equipment, $sizeMm), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.png"',
            ]),
        };
    }

    public function bulkLabels(
        PrintEquipmentLabelsRequest $request,
        EquipmentLabelService $labels,
    ): StreamedResponse {
        $items = Equipment::query()
            ->whereIn('id', $request->validated('ids'))
            ->orderBy('equipment_code')
            ->get();

        $zpl = $labels->bulkZpl($items);

        return response()->streamDownload(
            static fn () => print ($zpl),
            'equipment-labels.zpl',
            ['Content-Type' => 'text/plain'],
        );
    }

    public function printLabel(
        Equipment $equipment,
        EquipmentLabelService $labels,
    ): RedirectResponse|StreamedResponse {
        $this->authorize('print', $equipment);

        $result = $labels->printLabels([$equipment]);

        if (! $result['sent']) {
            return response()->streamDownload(
                static fn () => print ($result['zpl'] ?? ''),
                $equipment->equipment_code.'.zpl',
                ['Content-Type' => 'text/plain'],
            );
        }

        return back()->with('flash', ['success' => $result['message']]);
    }

    public function printLabels(
        PrintEquipmentLabelsRequest $request,
        EquipmentLabelService $labels,
    ): RedirectResponse|StreamedResponse {
        $items = Equipment::query()->whereIn('id', $request->validated('ids'))->get();
        $result = $labels->printLabels($items);

        if (! $result['sent']) {
            return response()->streamDownload(
                static fn () => print ($result['zpl'] ?? ''),
                'equipment-labels.zpl',
                ['Content-Type' => 'text/plain'],
            );
        }

        return back()->with('flash', ['success' => $result['message']]);
    }

    public function importForm(): InertiaResponse
    {
        $this->authorize('import', Equipment::class);

        $latest = EquipmentImport::query()->latest('id')->first();

        return Inertia::render('equipment/import', [
            'latestImport' => $latest === null ? null : [
                'id' => $latest->id,
                'original_filename' => $latest->original_filename,
                'status' => $latest->status,
                'summary' => $latest->summary,
                'created_at' => optional($latest->created_at)?->toIso8601String(),
            ],
        ]);
    }

    public function import(
        ImportEquipmentRequest $request,
        EquipmentService $equipmentService,
    ): RedirectResponse {
        $import = $equipmentService->beginImport($request->file('file'), (int) $request->user()->id);
        ImportEquipmentJob::dispatch($import->id);

        return redirect()
            ->route('equipment.import')
            ->with('flash', [
                'success' => 'Import queued.',
                'import_id' => $import->id,
            ]);
    }

    public function importTemplate(): StreamedResponse
    {
        $this->authorize('import', Equipment::class);

        $headers = [
            'equipment_code',
            'name',
            'equipment_type',
            'location_label',
            'description',
            'inspection_interval_days',
            'service_interval_days',
            'last_inspection_date',
            'notes',
        ];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, $headers);
            fclose($out);
        }, 'equipment-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
