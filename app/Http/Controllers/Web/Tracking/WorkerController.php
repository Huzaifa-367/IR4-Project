<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Enums\WorkerType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Tracking\ImportWorkersRequest;
use App\Http\Requests\Tracking\StoreWorkerRequest;
use App\Http\Requests\Tracking\UpdateWorkerRequest;
use App\Http\Resources\WorkerResource;
use App\Jobs\ImportWorkersJob;
use App\Models\EntryExitLog;
use App\Models\HseIncident;
use App\Models\IncidentPersonnel;
use App\Models\LsrViolation;
use App\Models\PortableDevice;
use App\Models\RfidTag;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Models\WorkerDocumentType;
use App\Models\WorkerImport;
use App\Services\SignedStorageUrlService;
use App\Services\WorkerDocumentReadinessService;
use App\Services\WorkerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkerController extends BaseController
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Worker::class);

        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        $query = Worker::query();

        if ($request->filled('contractor')) {
            $query->where('contractor', $request->string('contractor')->toString());
        }

        if ($request->filled('worker_type')) {
            $query->where('worker_type', $request->string('worker_type')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->where('is_active', true);
        }

        if ($request->has('present')) {
            $query->where('present', $request->boolean('present'));
        }

        $searchable = $canSeeIdentity
            ? ['name', 'contractor', 'role_title', 'badge_number', 'employee_code']
            : ['contractor', 'role_title'];

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['name', 'contractor', 'worker_type', 'is_active', 'present', 'created_at'],
            searchable: $searchable,
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('workforce/workers/index', [
            'workers' => [
                'data' => WorkerResource::collection($paginator->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'contractor' => $request->string('contractor')->toString(),
                'worker_type' => $request->string('worker_type')->toString(),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                'present' => $request->has('present') ? $request->boolean('present') : null,
                'sort' => $request->string('sort')->toString() ?: 'name',
                'direction' => $request->string('direction')->toString() ?: 'asc',
            ],
            'workerTypes' => collect(WorkerType::cases())->map(fn (WorkerType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'canManage' => ($request->user()?->can('create-workers') || $request->user()?->can('update-workers') || $request->user()?->can('delete-workers')) ?? false,
            'canSeeIdentity' => $canSeeIdentity,
            'openCreate' => $request->boolean('create'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Worker::class);

        return redirect()->route('tracking.workers.index', ['create' => 1]);
    }

    public function store(StoreWorkerRequest $request, WorkerService $workers): RedirectResponse
    {
        $worker = $workers->create($request->validated());

        return redirect()
            ->route('tracking.workers.show', [
                'worker' => $worker,
                'onboarding' => 1,
            ])
            ->with('flash', ['success' => 'Worker created. Add certificates next so they can be assigned to permits.']);
    }

    public function show(
        Request $request,
        Worker $worker,
        SignedStorageUrlService $signedUrls,
        WorkerDocumentReadinessService $readiness,
    ): InertiaResponse {
        $this->authorize('view', $worker);

        $user = $request->user();
        $canSeeEntryExit = $user?->can('view-entry-exit') ?? false;
        $canSeePortableDevices = $user?->can('view-portable-devices') ?? false;
        $canSeeIncidents = $user?->can('view-incidents') ?? false;
        $canSeeLsr = $user?->can('view-lsr') ?? false;
        $canManageDocuments = ($user?->can('view-worker-documents') || $user?->can('create-worker-documents') || $user?->can('update-worker-documents')) ?? false;

        return Inertia::render('workforce/workers/show', [
            'worker' => (new WorkerResource($worker))->resolve($request),
            'onboarding' => $request->boolean('onboarding'),
            'openEdit' => $request->boolean('edit'),
            'canManage' => ($user?->can('create-workers') || $user?->can('update-workers') || $user?->can('delete-workers')) ?? false,
            'canSeeEntryExit' => $canSeeEntryExit,
            'canSeePortableDevices' => $canSeePortableDevices,
            'canSeeIncidents' => $canSeeIncidents,
            'canSeeLsr' => $canSeeLsr,
            'tagHistory' => RfidTag::query()
                ->where('worker_id', $worker->id)
                ->orderByDesc('assigned_at')
                ->limit(10)
                ->get(['id', 'tag_uid', 'status', 'assigned_at'])
                ->map(fn (RfidTag $tag): array => [
                    'id' => $tag->id,
                    'tag_uid' => $tag->tag_uid,
                    'status' => $tag->status->value,
                    'status_label' => $tag->status->label(),
                    'assigned_at' => $tag->assigned_at?->toIso8601String(),
                ]),
            'entryExitLogs' => $canSeeEntryExit
                ? EntryExitLog::query()
                    ->where('worker_id', $worker->id)
                    ->with('gateZone:id,name')
                    ->orderByDesc('occurred_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (EntryExitLog $log): array => [
                        'id' => $log->id,
                        'direction' => $log->direction->value,
                        'occurred_at' => $log->occurred_at?->toIso8601String(),
                        'source' => $log->source->value,
                        'gate_zone_name' => $log->gateZone?->name,
                        'correction_note' => $log->correction_note,
                    ])
                : [],
            'portableDevices' => $canSeePortableDevices
                ? PortableDevice::query()
                    ->where('worker_id', $worker->id)
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn (PortableDevice $device): array => [
                        'id' => $device->id,
                        'device_type' => $device->device_type,
                        'make_model' => $device->make_model,
                        'serial_number' => $device->serial_number,
                        'status' => $device->status->value,
                        'approved_at' => $device->approved_at?->toIso8601String(),
                        'revoked_at' => $device->revoked_at?->toIso8601String(),
                        'revoke_reason' => $device->revoke_reason,
                    ])
                : [],
            'incidents' => $canSeeIncidents
                ? $worker->incidentPersonnel()
                    ->whereHas('incident')
                    ->with('incident:id,incident_number,status')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(function (IncidentPersonnel $row): array {
                        /** @var HseIncident $incident */
                        $incident = $row->incident;

                        return [
                            'id' => $incident->id,
                            'incident_number' => $incident->incident_number,
                            'status_label' => $incident->status->label(),
                            'involvement_label' => $row->involvement->label(),
                        ];
                    })
                : [],
            'lsrViolations' => $canSeeLsr
                ? $worker->lsrViolations()
                    ->orderByDesc('occurred_at')
                    ->limit(10)
                    ->get(['id', 'category', 'status', 'occurred_at'])
                    ->map(fn (LsrViolation $lsr): array => [
                        'id' => $lsr->id,
                        'category_label' => $lsr->category->label(),
                        'status_label' => $lsr->status->label(),
                        'occurred_at' => $lsr->occurred_at?->toIso8601String(),
                    ])
                : [],
            'canManageDocuments' => $canManageDocuments,
            'documentChecklist' => $canManageDocuments ? $readiness->checklist($worker) : [],
            'permitReadiness' => $canManageDocuments ? $readiness->roleMatrix($worker) : [],
            'readinessSummary' => $canManageDocuments ? $readiness->summary($worker) : null,
            'documents' => $canManageDocuments
                ? $worker->documents()
                    ->with('documentType:id,code,name,requires_file')
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn (WorkerDocument $document): array => [
                        'id' => $document->id,
                        'worker_document_type_id' => $document->worker_document_type_id,
                        'type_name' => $document->documentType?->name ?? '—',
                        'document_number' => $document->document_number,
                        'issuing_body' => $document->issuing_body,
                        'issued_at' => $document->issued_at?->toDateString(),
                        'expires_at' => $document->expires_at?->toDateString(),
                        'notes' => $document->notes,
                        'verification_status' => $document->verification_status->value,
                        'verification_status_label' => $document->verification_status->label(),
                        'has_file' => $document->file_path !== null && $document->file_path !== '',
                        'download_url' => ($document->file_path !== null && $document->file_path !== '')
                            ? $signedUrls->temporaryUrl($document->file_path)
                            : null,
                    ])
                : [],
            'documentTypes' => $canManageDocuments
                ? WorkerDocumentType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'requires_file', 'requires_expiry', 'category'])
                    ->map(fn (WorkerDocumentType $type): array => [
                        'id' => $type->id,
                        'name' => $type->name,
                        'code' => $type->code,
                        'requires_file' => $type->requires_file,
                        'requires_expiry' => $type->requires_expiry,
                        'category' => $type->category,
                    ])
                : [],
            'workerTypes' => collect(WorkerType::cases())->map(fn (WorkerType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function edit(Worker $worker): RedirectResponse
    {
        $this->authorize('update', $worker);

        return redirect()->route('tracking.workers.show', [
            'worker' => $worker,
            'edit' => 1,
        ]);
    }

    public function update(UpdateWorkerRequest $request, Worker $worker, WorkerService $workers): RedirectResponse
    {
        $workers->update($worker, $request->validated());

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Worker updated.']);
    }

    public function deactivate(Worker $worker, WorkerService $workers): RedirectResponse
    {
        $this->authorize('update', $worker);
        $workers->deactivate($worker);

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Worker deactivated.']);
    }

    public function reactivate(Worker $worker, WorkerService $workers): RedirectResponse
    {
        $this->authorize('update', $worker);
        $workers->reactivate($worker);

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Worker reactivated.']);
    }

    public function offboard(Worker $worker, WorkerService $workers): RedirectResponse
    {
        $this->authorize('update', $worker);
        $workers->offboard($worker);

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Worker offboarded.']);
    }

    public function destroy(Worker $worker, WorkerService $workers): RedirectResponse
    {
        $this->authorize('delete', $worker);
        $workers->destroy($worker);

        return redirect()
            ->route('tracking.workers.index')
            ->with('flash', ['success' => 'Worker deleted.']);
    }

    public function importForm(Request $request): InertiaResponse
    {
        $this->authorize('import', Worker::class);

        $latest = WorkerImport::query()
            ->where('created_by', $request->user()?->id)
            ->latest('id')
            ->first();

        return Inertia::render('workforce/workers/import', [
            'latestImport' => $latest === null ? null : [
                'id' => $latest->id,
                'original_filename' => $latest->original_filename,
                'status' => $latest->status,
                'summary' => $latest->summary,
                'created_at' => $latest->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function import(ImportWorkersRequest $request, WorkerService $workers): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $import = $workers->beginImport($file, (int) $request->user()->id);

        ImportWorkersJob::dispatch($import->id);

        return redirect()
            ->route('tracking.workers.import')
            ->with('flash', ['success' => 'Import queued.']);
    }

    public function template(): StreamedResponse|Response
    {
        $this->authorize('import', Worker::class);

        $csv = "name,contractor,worker_type,role_title,badge_number,employee_code,phone,notes\n";
        $csv .= "Jane Doe,ACME Contracting,contractor,Rigger,BDG-1001,EMP-1001,,\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="workers-import-template.csv"',
        ]);
    }
}
