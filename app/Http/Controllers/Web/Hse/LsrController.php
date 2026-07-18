<?php

namespace App\Http\Controllers\Web\Hse;

use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Hse\BulkCloseLsrViolationRequest;
use App\Http\Requests\Web\Hse\CloseLsrViolationRequest;
use App\Http\Requests\Web\Hse\StoreLsrViolationRequest;
use App\Models\Alert;
use App\Models\LsrViolation;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\LsrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class LsrController extends BaseController
{
    public function index(Request $request, LsrService $lsr): InertiaResponse
    {
        $this->authorize('viewAny', LsrViolation::class);

        $query = LsrViolation::query()->with(['worker', 'zone', 'logger']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['occurred_at', 'category', 'status', 'created_at'],
            searchable: ['description', 'action_taken'],
            defaultSort: 'occurred_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('hse/lsr/index', [
            'violations' => [
                'data' => $paginator->getCollection()
                    ->map(fn (LsrViolation $row): array => $lsr->toArray($row, $canSeeIdentity))
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
                'category' => $request->string('category')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'occurred_at',
                'direction' => $request->string('direction')->toString() ?: 'desc',
            ],
            'categoryOptions' => collect(LsrCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'statusOptions' => collect(LsrStatus::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'canLog' => $request->user()?->can('log-lsr') ?? false,
            'canClose' => $request->user()?->can('close-lsr') ?? false,
            'prefill' => $this->resolvePrefill($request, $lsr),
        ]);
    }

    public function store(StoreLsrViolationRequest $request, LsrService $lsr): RedirectResponse
    {
        $violation = $lsr->create($request->validated(), $request->user());

        return redirect()
            ->route('hse.lsr.index', ['status' => 'open'])
            ->with('flash', ['success' => 'LSR logged #'.$violation->id]);
    }

    public function show(Request $request, LsrViolation $lsr, LsrService $service): InertiaResponse
    {
        $this->authorize('view', $lsr);
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('hse/lsr/show', [
            'violation' => $service->toArray($lsr, $canSeeIdentity),
            'canClose' => $request->user()?->can('close-lsr') ?? false,
        ]);
    }

    public function close(
        CloseLsrViolationRequest $request,
        LsrViolation $lsr,
        LsrService $service,
    ): RedirectResponse {
        $service->close($lsr, $request->validated(), $request->user());

        return back()->with('flash', ['success' => 'LSR closed.']);
    }

    public function closeBulk(BulkCloseLsrViolationRequest $request, LsrService $service): RedirectResponse
    {
        $service->closeBulk(
            $request->validated('ids'),
            $request->validated('action_taken'),
            $request->user(),
        );

        return back()->with('flash', ['success' => 'LSR violations closed.']);
    }

    public function summary(Request $request, LsrService $service): InertiaResponse
    {
        $this->authorize('viewAny', LsrViolation::class);

        $from = $request->filled('from') ? $request->date('from') : now()->subDays(7);
        $to = $request->filled('to') ? $request->date('to') : now();

        return Inertia::render('hse/lsr/summary', [
            'summary' => $service->summary($from, $to),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    public function apiSummary(Request $request, LsrService $service): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', LsrViolation::class);

        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        return response()->json($service->summary($from, $to));
    }

    public function createForm(Request $request, LsrService $lsr): InertiaResponse
    {
        $this->authorize('create', LsrViolation::class);
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return Inertia::render('hse/lsr/create', [
            'prefill' => $this->resolvePrefill($request, $lsr),
            'categoryOptions' => collect(LsrCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'zones' => Zone::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(
                fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                ],
            )->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePrefill(Request $request, LsrService $lsr): ?array
    {
        if (! $request->filled('alert_id')) {
            return null;
        }

        $alert = Alert::query()->find((int) $request->integer('alert_id'));

        return $alert !== null ? $lsr->prefillFromAlert($alert) : null;
    }
}
