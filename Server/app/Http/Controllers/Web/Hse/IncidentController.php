<?php

namespace App\Http\Controllers\Web\Hse;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Hse\ClassifyHseIncidentRequest;
use App\Http\Requests\Web\Hse\CloseHseIncidentRequest;
use App\Http\Requests\Web\Hse\StoreHseIncidentRequest;
use App\Http\Requests\Web\Hse\StoreIncidentEvidenceRequest;
use App\Models\Alert;
use App\Models\HseIncident;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class IncidentController extends BaseController
{
    public function index(Request $request, IncidentService $incidents): InertiaResponse
    {
        $this->authorize('viewAny', HseIncident::class);

        $query = HseIncident::query()->with(['zone', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('source')) {
            $query->where('source', $request->string('source')->toString());
        }
        if ($request->filled('incident_type')) {
            $query->where('incident_type', $request->string('incident_type')->toString());
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['occurred_at', 'incident_number', 'status', 'created_at'],
            searchable: ['incident_number', 'nature_of_incident'],
            defaultSort: 'occurred_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('hse/incidents/index', [
            'incidents' => [
                'data' => $paginator->getCollection()
                    ->map(fn (HseIncident $row): array => $incidents->toArray($row, $canSeeIdentity))
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
                'source' => $request->string('source')->toString(),
                'incident_type' => $request->string('incident_type')->toString(),
                'severity' => $request->string('severity')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'occurred_at',
                'direction' => $request->string('direction')->toString() ?: 'desc',
            ],
            'statusOptions' => collect(IncidentStatus::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'sourceOptions' => collect(IncidentSource::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'typeOptions' => collect(IncidentType::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'severityOptions' => collect(IncidentSeverity::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'canLog' => $request->user()?->can('create-incidents') ?? false,
            'canClassify' => $request->user()?->can('update-incidents') ?? false,
            'prefill' => $this->resolvePrefill($request, $incidents),
            'zones' => ($request->user()?->can('create-incidents') ?? false)
                ? Zone::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name'])
                : [],
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->authorize('create', HseIncident::class);

        return redirect()->route('hse.incidents.index', $request->only('alert_id'));
    }

    public function store(StoreHseIncidentRequest $request, IncidentService $incidents): RedirectResponse
    {
        $incident = $incidents->create($request->validated(), $request->user());

        return redirect()
            ->route('hse.incidents.show', $incident)
            ->with('flash', ['success' => 'Incident logged.']);
    }

    public function show(Request $request, HseIncident $incident, IncidentService $incidents): InertiaResponse
    {
        $this->authorize('view', $incident);

        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('hse/incidents/show', [
            'incident' => $incidents->toArray($incident, $canSeeIdentity),
            'workers' => Worker::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name'])->map(
                fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'uuid' => $worker->uuid,
                    'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                ],
            )->values()->all(),
            'typeOptions' => collect(IncidentType::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'severityOptions' => collect(IncidentSeverity::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'canLog' => $request->user()?->can('create-incidents') ?? false,
            'canClassify' => $request->user()?->can('update-incidents') ?? false,
        ]);
    }

    public function classify(
        ClassifyHseIncidentRequest $request,
        HseIncident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $incidents->classify($incident, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Incident classified.']);
    }

    public function reopen(HseIncident $incident, IncidentService $incidents): RedirectResponse
    {
        $this->authorize('reopen', $incident);
        $incidents->reopen($incident, request()->user());

        return back()->with('flash', ['success' => 'Incident reopened.']);
    }

    public function close(
        CloseHseIncidentRequest $request,
        HseIncident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $incidents->close($incident, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Incident closed.']);
    }

    public function storeEvidence(
        StoreIncidentEvidenceRequest $request,
        HseIncident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $incidents->addEvidence($incident, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'Evidence attached.']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePrefill(Request $request, IncidentService $incidents): ?array
    {
        if (! $request->filled('alert_id')) {
            return null;
        }

        $alert = Alert::query()->find((int) $request->integer('alert_id'));

        return $alert !== null ? $incidents->prefillFromAlert($alert) : null;
    }
}
