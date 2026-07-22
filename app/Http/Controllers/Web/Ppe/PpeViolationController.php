<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Enums\AuditEvent;
use App\Enums\ReviewStatus;
use App\Enums\ViolationType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Ppe\BulkReviewPpeViolationRequest;
use App\Http\Requests\Web\Ppe\ExportPpeViolationsRequest;
use App\Http\Requests\Web\Ppe\ReviewPpeViolationRequest;
use App\Models\Camera;
use App\Models\PpeViolation;
use App\Services\AuditService;
use App\Services\PpeViolationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class PpeViolationController extends BaseController
{
    public function index(Request $request, PpeViolationService $ppe): InertiaResponse
    {
        $this->authorize('viewAny', PpeViolation::class);

        $query = PpeViolation::query()->with(['camera', 'reviewer']);

        if ($request->filled('violation_type')) {
            $query->where('violation_type', $request->string('violation_type')->toString());
        }
        if ($request->filled('camera_id')) {
            $query->where('camera_id', $request->integer('camera_id'));
        }
        if ($request->filled('review_status')) {
            $query->where('review_status', $request->string('review_status')->toString());
        }
        if ($request->filled('from')) {
            $query->where('detected_at', '>=', Carbon::parse($request->string('from')->toString()));
        }
        if ($request->filled('to')) {
            $query->where('detected_at', '<=', Carbon::parse($request->string('to')->toString()));
        }
        if ($request->has('is_backfill')) {
            $query->where('is_backfill', $request->boolean('is_backfill'));
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['detected_at', 'violation_type', 'review_status', 'confidence'],
            searchable: ['location_label'],
            defaultSort: 'detected_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('ppe/violations/index', [
            'violations' => [
                'data' => $paginator->getCollection()->map(fn (PpeViolation $v) => $ppe->toArray($v))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'violation_type' => $request->string('violation_type')->toString(),
                'camera_id' => $request->string('camera_id')->toString(),
                'review_status' => $request->string('review_status')->toString(),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'is_backfill' => $request->string('is_backfill')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'cameras' => Camera::query()->orderBy('name')->get(['id', 'uuid', 'name', 'reference']),
            'violationTypes' => collect(ViolationType::cases())->map(fn (ViolationType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'reviewStatuses' => collect(ReviewStatus::cases())->map(fn (ReviewStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'canReview' => $request->user()?->can('update-ppe-violations') ?? false,
            'canExport' => $request->user()?->can('export-ppe-violations') ?? false,
        ]);
    }

    public function show(PpeViolation $violation, PpeViolationService $ppe): InertiaResponse
    {
        $this->authorize('view', $violation);

        return Inertia::render('ppe/violations/show', [
            'violation' => $ppe->toArray($violation),
            'canReview' => request()->user()?->can('review', $violation) ?? false,
        ]);
    }

    public function review(
        ReviewPpeViolationRequest $request,
        PpeViolation $violation,
        PpeViolationService $ppe,
    ): RedirectResponse {
        $ppe->review($violation, $request->user(), $request->validated());

        return redirect()->back();
    }

    public function bulkReview(
        BulkReviewPpeViolationRequest $request,
        PpeViolationService $ppe,
    ): RedirectResponse {
        $data = $request->validated();
        $ppe->bulkReview($data['ids'], $request->user(), [
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->back();
    }

    public function export(
        ExportPpeViolationsRequest $request,
        PpeViolationService $ppe,
        AuditService $audit,
    ): Response
    {
        $data = $request->validated();
        $audit->record(
            AuditEvent::Exported,
            description: 'Exported PPE violations.',
            newValues: $data,
            user: $request->user(),
        );

        return $ppe->export(
            $data['format'],
            Carbon::parse($data['from'])->startOfDay(),
            Carbon::parse($data['to'])->endOfDay(),
        );
    }

    public function summary(Request $request, PpeViolationService $ppe): JsonResponse
    {
        $this->authorize('viewAny', PpeViolation::class);

        $range = $request->string('range', 'weekly')->toString();
        $groupBy = $request->string('group_by', 'type')->toString();

        [$from, $to] = match ($range) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'custom' => [
                Carbon::parse($request->string('from')->toString())->startOfDay(),
                Carbon::parse($request->string('to')->toString())->endOfDay(),
            ],
            default => [now()->startOfWeek(), now()->endOfWeek()],
        };

        return ApiResponse::ok($ppe->summary($from, $to, $groupBy));
    }

    public function recent(Request $request, PpeViolationService $ppe): JsonResponse
    {
        $this->authorize('viewAny', PpeViolation::class);

        $rows = PpeViolation::query()
            ->with('camera')
            ->where('review_status', ReviewStatus::Unreviewed)
            ->where('is_backfill', false)
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->map(fn (PpeViolation $v) => $ppe->toArray($v))
            ->values();

        return ApiResponse::ok(['violations' => $rows]);
    }
}
