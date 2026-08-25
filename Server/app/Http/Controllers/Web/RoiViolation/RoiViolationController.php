<?php

namespace App\Http\Controllers\Web\RoiViolation;

use App\Enums\ReviewStatus;
use App\Enums\RoiViolationType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\RoiViolation\BulkReviewRoiViolationRequest;
use App\Http\Requests\Web\RoiViolation\ReviewRoiViolationRequest;
use App\Models\Camera;
use App\Models\RoiViolation;
use App\Services\Camera\RoiViolationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class RoiViolationController extends BaseController
{
    public function index(Request $request, RoiViolationService $rois): InertiaResponse
    {
        $this->authorize('viewAny', RoiViolation::class);

        $query = RoiViolation::query()->with(['camera', 'reviewer', 'cameraRoi']);

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type')->toString());
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

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['detected_at', 'event_type', 'review_status', 'confidence'],
            searchable: ['location_label', 'roi_reference'],
            defaultSort: 'detected_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('roi-violations/index', [
            'violations' => [
                'data' => $paginator->getCollection()->map(fn (RoiViolation $v) => $rois->toArray($v))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'event_type' => $request->string('event_type')->toString(),
                'camera_id' => $request->string('camera_id')->toString(),
                'review_status' => $request->string('review_status')->toString(),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'cameras' => Camera::query()->operational()->orderBy('name')->get(['id', 'uuid', 'name', 'reference']),
            'eventTypes' => collect(RoiViolationType::cases())->map(fn (RoiViolationType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'reviewStatuses' => collect(ReviewStatus::cases())->map(fn (ReviewStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'canReview' => $request->user()?->can('update-roi-violations') ?? false,
        ]);
    }

    public function show(RoiViolation $violation, RoiViolationService $rois): InertiaResponse
    {
        $this->authorize('view', $violation);

        return Inertia::render('roi-violations/show', [
            'violation' => $rois->toArray($violation),
            'canReview' => request()->user()?->can('review', $violation) ?? false,
        ]);
    }

    public function review(
        ReviewRoiViolationRequest $request,
        RoiViolation $violation,
        RoiViolationService $rois,
    ): RedirectResponse {
        $rois->review($violation, $request->user(), $request->validated());

        return redirect()->back();
    }

    public function bulkReview(
        BulkReviewRoiViolationRequest $request,
        RoiViolationService $rois,
    ): RedirectResponse {
        $data = $request->validated();
        $rois->bulkReview($data['ids'], $request->user(), [
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->back();
    }
}
