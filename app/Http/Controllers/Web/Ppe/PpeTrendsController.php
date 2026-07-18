<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\PpeViolation;
use App\Services\PpeViolationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PpeTrendsController extends BaseController
{
    public function __invoke(Request $request, PpeViolationService $ppe): InertiaResponse
    {
        $this->authorize('viewAny', PpeViolation::class);

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfWeek();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfWeek();

        return Inertia::render('ppe/trends/index', [
            'summary' => $ppe->summary($from, $to),
            'filters' => [
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
