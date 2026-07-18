<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Enums\AuditEvent;
use App\Enums\EvacuationStatus;
use App\Enums\MusterStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\EvacuationReport;
use App\Models\EvacuationReportEntry;
use App\Services\AuditService;
use App\Services\EvacuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class EvacuationController extends BaseController
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can('trigger-evacuation')
            || $request->user()?->can('manage-evacuation'),
            403,
        );

        $open = EvacuationReport::query()
            ->where('status', EvacuationStatus::Open)
            ->with('entries')
            ->latest('id')
            ->first();

        $history = EvacuationReport::query()
            ->orderByDesc('triggered_at')
            ->limit(20)
            ->get(['id', 'status', 'triggered_at', 'closed_at', 'force_closed']);

        return Inertia::render('tracking/evacuation/index', [
            'openReport' => $open ? $this->serializeReport($open) : null,
            'history' => $history,
            'canTrigger' => $request->user()?->can('trigger-evacuation') ?? false,
            'canManage' => $request->user()?->can('manage-evacuation') ?? false,
        ]);
    }

    public function show(Request $request, EvacuationReport $evacuation): Response
    {
        abort_unless(
            $request->user()?->can('trigger-evacuation')
            || $request->user()?->can('manage-evacuation'),
            403,
        );

        $evacuation->load(['entries.worker', 'entries.lastZone']);

        return Inertia::render('tracking/evacuation/show', [
            'report' => $this->serializeReport($evacuation),
            'canManage' => $request->user()?->can('manage-evacuation') ?? false,
        ]);
    }

    public function store(Request $request, EvacuationService $evac): RedirectResponse
    {
        abort_unless($request->user()?->can('trigger-evacuation'), 403);
        $report = $evac->trigger($request->user());

        return redirect()->route('tracking.evacuation.show', $report);
    }

    public function account(
        Request $request,
        EvacuationReport $evacuation,
        EvacuationReportEntry $entry,
        EvacuationService $evac,
    ): RedirectResponse {
        abort_unless($request->user()?->can('manage-evacuation'), 403);
        abort_unless($entry->evacuation_report_id === $evacuation->id, 404);

        $evac->accountManual($entry, $request->user());

        return redirect()->back();
    }

    public function close(Request $request, EvacuationReport $evacuation, EvacuationService $evac): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-evacuation'), 403);

        $data = $request->validate([
            'force' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $evac->close(
            $evacuation,
            $request->user(),
            (bool) ($data['force'] ?? false),
            $data['note'] ?? null,
        );

        return redirect()->back();
    }

    public function download(
        EvacuationReport $evacuation,
        EvacuationService $evac,
        AuditService $audit,
    ): SymfonyResponse
    {
        abort_unless(
            request()->user()?->can('manage-evacuation')
            || request()->user()?->can('trigger-evacuation'),
            403,
        );

        $audit->record(
            AuditEvent::Exported,
            $evacuation,
            'Exported evacuation report PDF.',
            user: request()->user(),
        );

        return $evac->downloadPdf($evacuation);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(EvacuationReport $report): array
    {
        $report->loadMissing(['entries.worker', 'entries.lastZone']);

        return [
            'id' => $report->id,
            'status' => $report->status->value,
            'triggered_at' => $report->triggered_at->toIso8601String(),
            'closed_at' => $report->closed_at?->toIso8601String(),
            'force_closed' => $report->force_closed,
            'close_note' => $report->close_note,
            'accounted' => $report->entries->where('muster_status', MusterStatus::Accounted)->count(),
            'total' => $report->entries->count(),
            'entries' => $report->entries->map(fn (EvacuationReportEntry $e) => [
                'id' => $e->id,
                'worker_id' => $e->worker_id,
                'worker_name' => $e->worker?->name,
                'last_zone' => $e->lastZone?->name,
                'last_seen_at' => $e->last_seen_at?->toIso8601String(),
                'muster_status' => $e->muster_status->value,
                'accounted_source' => $e->accounted_source?->value,
                'accounted_at' => $e->accounted_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
