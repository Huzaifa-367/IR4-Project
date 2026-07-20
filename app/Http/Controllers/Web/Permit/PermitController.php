<?php

namespace App\Http\Controllers\Web\Permit;

use App\Enums\GasTestPhase;
use App\Enums\GasTestSource;
use App\Enums\PermitStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Permit\InspectPermitRequest;
use App\Http\Requests\Web\Permit\NoteRequest;
use App\Http\Requests\Web\Permit\RecordGasTestRequest;
use App\Http\Requests\Web\Permit\StorePermitRequest;
use App\Http\Requests\Web\Permit\UpdatePermitRequest;
use App\Models\Permit;
use App\Models\PermitType;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Services\PermitService;
use App\Services\WorkerDocumentReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PermitController extends BaseController
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Permit::class);

        $query = Permit::query()->with(['type', 'zone']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['permit_number', 'status', 'valid_to', 'created_at'],
            searchable: ['permit_number', 'task_description'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('workforce/permits/index', [
            'permits' => [
                'data' => $paginator->getCollection()->map(fn (Permit $row): array => [
                    'id' => $row->id,
                    'permit_number' => $row->permit_number,
                    'status' => $row->status->value,
                    'status_label' => $row->status->label(),
                    'task_description' => $row->task_description,
                    'valid_to' => $row->valid_to?->toIso8601String(),
                    'type' => $row->type === null ? null : [
                        'id' => $row->type->id,
                        'name' => $row->type->name,
                        'colour_token' => $row->type->colour_token,
                    ],
                    'zone' => $row->zone === null ? null : [
                        'id' => $row->zone->id,
                        'name' => $row->zone->name,
                    ],
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
                'status' => $request->string('status')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'created_at',
                'direction' => $request->string('direction')->toString() ?: 'desc',
            ],
            'statusOptions' => collect(PermitStatus::cases())->map(fn (PermitStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'canRequest' => $request->user()?->can('request-permit') ?? false,
        ]);
    }

    public function create(Request $request, WorkerDocumentReadinessService $readiness): InertiaResponse
    {
        $this->authorize('create', Permit::class);

        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        $permitTypes = PermitType::query()
            ->where('is_active', true)
            ->with([
                'roles' => fn ($query) => $query->orderBy('sort_order'),
                'gasChannels' => fn ($query) => $query->orderBy('sort_order'),
                'checklistItems' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('label'),
                'documentRequirements.workerDocumentType',
            ])
            ->orderBy('sort_order')
            ->get();

        $workers = Worker::query()
            ->where('is_active', true)
            ->with(['documents.documentType'])
            ->orderBy('name')
            ->get();

        return Inertia::render('workforce/permits/create', [
            'permitTypes' => $permitTypes
                ->map(fn (PermitType $type): array => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'colour_token' => $type->colour_token,
                    'requires_gas_test' => $type->requires_gas_test,
                    'requires_joint_inspection' => $type->requires_joint_inspection,
                    'requires_approver' => $type->requires_approver,
                    'allows_extended' => $type->allows_extended,
                    'roles' => $type->roles->map(fn ($role): array => [
                        'role_code' => $role->role_code,
                        'label' => $role->label,
                        'min_count' => $role->min_count,
                        'is_mandatory' => $role->is_mandatory,
                    ])->values()->all(),
                    'gas_channels' => $type->gasChannels->map(fn ($channel): array => [
                        'channel_code' => $channel->channel_code,
                        'label' => $channel->label,
                        'unit' => $channel->unit,
                        'alarm_below' => $channel->alarm_below,
                        'alarm_above' => $channel->alarm_above,
                    ])->values()->all(),
                    'document_requirements' => $type->documentRequirements->map(fn ($req): array => [
                        'role_code' => $req->role_code,
                        'is_mandatory' => $req->is_mandatory,
                        'must_be_verified' => $req->must_be_verified,
                        'worker_document_type' => $req->workerDocumentType === null ? null : [
                            'id' => $req->workerDocumentType->id,
                            'code' => $req->workerDocumentType->code,
                            'name' => $req->workerDocumentType->name,
                        ],
                    ])->values()->all(),
                    'checklist_items' => $type->checklistItems->map(fn ($item): array => [
                        'id' => $item->id,
                        'code' => $item->code,
                        'label' => $item->label,
                        'is_mandatory' => $item->is_mandatory,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'zones' => Zone::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'requires_permit']),
            'workOrders' => WorkOrder::query()
                ->where('status', 'open')
                ->with('zone:id,name')
                ->orderByDesc('created_at')
                ->get(['id', 'reference', 'zone_id'])
                ->map(fn (WorkOrder $row): array => [
                    'id' => $row->id,
                    'reference' => $row->reference,
                    'zone' => $row->zone === null ? null : [
                        'id' => $row->zone->id,
                        'name' => $row->zone->name,
                    ],
                ])
                ->values()
                ->all(),
            'workers' => $workers->map(function (Worker $worker) use ($canSeeIdentity, $readiness, $permitTypes): array {
                $roleEligibility = [];

                foreach ($permitTypes as $type) {
                    foreach ($type->roles as $role) {
                        $assessment = $readiness->assessRole($worker->id, $type, $role->role_code);
                        $roleEligibility[$type->id.':'.$role->role_code] = [
                            'ready' => $assessment['ready'],
                            'missing' => $assessment['missing'],
                            'missing_labels' => $assessment['missing_labels'],
                        ];
                    }
                }

                return [
                    'id' => $worker->id,
                    'label' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                    'reference' => $canSeeIdentity ? $worker->employee_code : null,
                    'verified_document_codes' => $readiness->gateSatisfyingCodes($worker),
                    'role_eligibility' => $roleEligibility,
                ];
            })->values()->all(),
            'initialWorkOrderId' => $request->filled('work_order_id')
                ? (int) $request->input('work_order_id')
                : null,
        ]);
    }

    public function store(StorePermitRequest $request, PermitService $permits): RedirectResponse
    {
        $permit = $permits->createDraft($request->user(), $request->validated());

        return redirect()
            ->route('permits.show', $permit)
            ->with('flash', ['success' => 'Permit draft created.']);
    }

    public function update(UpdatePermitRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $permits->updateDraft($permit, $request->user(), $request->validated());

        return redirect()
            ->route('permits.show', $permit)
            ->with('flash', ['success' => 'Permit draft updated.']);
    }

    public function show(
        Request $request,
        Permit $permit,
        PermitService $permits,
        WorkerDocumentReadinessService $readiness,
    ): InertiaResponse {
        $this->authorize('view', $permit);

        $user = $request->user();
        $permit->loadMissing([
            'type.gasChannels',
            'type.roles',
            'type.checklistItems' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('label'),
        ]);
        $canSeeIdentity = $user?->can('view-worker-identity') ?? false;
        $isEditable = in_array($permit->status, [PermitStatus::Draft, PermitStatus::Rejected], true);
        $canUpdate = $user?->can('update', $permit) ?? false;
        $type = $permit->type;

        $workers = [];

        if ($isEditable && $canUpdate && $type instanceof PermitType) {
            $workers = Worker::query()
                ->where('is_active', true)
                ->with(['documents.documentType'])
                ->orderBy('name')
                ->get()
                ->map(function (Worker $worker) use ($canSeeIdentity, $readiness, $type): array {
                    $roleEligibility = [];

                    foreach ($type->roles as $role) {
                        $assessment = $readiness->assessRole($worker->id, $type, $role->role_code);
                        $roleEligibility[(string) $type->id.':'.$role->role_code] = [
                            'ready' => $assessment['ready'],
                            'missing' => $assessment['missing'],
                            'missing_labels' => $assessment['missing_labels'],
                        ];
                    }

                    return [
                        'id' => $worker->id,
                        'label' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                        'reference' => $canSeeIdentity ? $worker->employee_code : null,
                        'role_eligibility' => $roleEligibility,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('workforce/permits/show', [
            'permit' => $permits->toArray($permit),
            'gasChannels' => ($type?->gasChannels ?? collect())->map(fn ($channel): array => [
                'channel_code' => $channel->channel_code,
                'label' => $channel->label,
                'unit' => $channel->unit,
                'alarm_below' => $channel->alarm_below,
                'alarm_above' => $channel->alarm_above,
            ])->values()->all(),
            'checklistItems' => ($type?->checklistItems ?? collect())->map(fn ($item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'is_mandatory' => $item->is_mandatory,
            ])->values()->all(),
            'zones' => $isEditable && $canUpdate
                ? Zone::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'requires_permit'])
                : [],
            'workers' => $workers,
            'typeRoles' => ($type?->roles ?? collect())->map(fn ($role): array => [
                'role_code' => $role->role_code,
                'label' => $role->label,
                'min_count' => $role->min_count,
                'is_mandatory' => $role->is_mandatory,
            ])->values()->all(),
            'allowsExtended' => (bool) ($type?->allows_extended ?? false),
            'gasPhaseOptions' => collect(GasTestPhase::cases())->map(fn (GasTestPhase $phase): array => [
                'value' => $phase->value,
                'label' => $phase->label(),
            ]),
            'canRequest' => $user?->can('request-permit') ?? false,
            'canUpdate' => $canUpdate,
            'canIssue' => $user?->can('issue', $permit) ?? false,
            'canApprove' => $user?->can('approve', $permit) ?? false,
            'canGasTest' => $user?->can('gasTest', $permit) ?? false,
            'canInspect' => $user?->can('inspect', $permit) ?? false,
        ]);
    }

    public function submit(Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('update', $permit);
        $permits->submit($permit, request()->user());

        return back()->with('flash', ['success' => 'Permit submitted.']);
    }

    public function inspect(InspectPermitRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('inspect', $permit);
        $permits->recordJointInspection($permit, $request->user(), $request->string('as')->toString());

        return back()->with('flash', ['success' => 'Joint inspection recorded.']);
    }

    public function storeGasTest(RecordGasTestRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $validated = $request->validated();

        $permits->recordGasTest(
            $permit,
            $request->user(),
            $validated['readings'],
            GasTestSource::from($validated['source']),
            $validated['device_id'] ?? null,
            GasTestPhase::from($validated['phase']),
        );

        return back()->with('flash', ['success' => 'Gas test recorded.']);
    }

    public function gasSuggestion(Permit $permit, PermitService $permits): JsonResponse
    {
        $this->authorize('gasTest', $permit);

        return response()->json([
            'readings' => $permits->suggestGasReadings($permit),
        ]);
    }

    public function approve(Request $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('approve', $permit);
        $permits->approve($permit, $request->user(), $request->string('note')->toString() ?: null);

        return back()->with('flash', ['success' => 'Permit approved.']);
    }

    public function issue(Request $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('issue', $permit);
        $permits->issue($permit, $request->user(), $request->string('note')->toString() ?: null);

        return back()->with('flash', ['success' => 'Permit issued.']);
    }

    public function suspend(NoteRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('suspend', $permit);
        $permits->suspend($permit, $request->user(), $request->string('note')->toString());

        return back()->with('flash', ['success' => 'Permit suspended.']);
    }

    public function resume(Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('resume', $permit);
        $permits->resume($permit, request()->user());

        return back()->with('flash', ['success' => 'Permit resumed.']);
    }

    public function renew(Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('issue', $permit);
        $permits->renew($permit, request()->user());

        return back()->with('flash', ['success' => 'Permit renewed.']);
    }

    public function cancel(NoteRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('cancel', $permit);
        $permits->cancel($permit, $request->user(), $request->string('note')->toString());

        return back()->with('flash', ['success' => 'Permit cancelled.']);
    }

    public function close(NoteRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('close', $permit);
        $permits->close($permit, $request->user(), $request->string('note')->toString());

        return back()->with('flash', ['success' => 'Permit closed.']);
    }

    public function reject(NoteRequest $request, Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('reject', $permit);
        $permits->reject($permit, $request->user(), $request->string('note')->toString());

        return back()->with('flash', ['success' => 'Permit rejected.']);
    }
}
