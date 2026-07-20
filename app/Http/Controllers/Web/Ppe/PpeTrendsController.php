<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\PpeViolation;
use App\Services\PpeViolationService;
use App\Support\TrendRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PpeTrendsController extends BaseController
{
    public function __invoke(Request $request, PpeViolationService $ppe): InertiaResponse
    {
        $this->authorize('viewAny', PpeViolation::class);

        [$range, $from, $to] = TrendRange::resolve($request);

        return Inertia::render('ppe/index', [
            'snapshot' => $ppe->dashboardSnapshot($from, $to),
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'unreviewedCount' => PpeViolation::query()
                ->where('review_status', ReviewStatus::Unreviewed)
                ->count(),
            'canExport' => $request->user()?->can('export-ppe-reports') ?? false,
        ]);
    }
}
