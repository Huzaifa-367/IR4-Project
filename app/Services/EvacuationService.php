<?php

namespace App\Services;

use App\Enums\AccountedSource;
use App\Enums\AlertType;
use App\Enums\EvacuationStatus;
use App\Enums\MusterStatus;
use App\Events\EvacuationEntryUpdated;
use App\Events\EvacuationTriggered;
use App\Models\AuditLog;
use App\Models\EvacuationReport;
use App\Models\EvacuationReportEntry;
use App\Models\User;
use App\Models\WorkerPosition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EvacuationService
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    public function trigger(User $by): EvacuationReport
    {
        $existing = EvacuationReport::query()
            ->where('status', EvacuationStatus::Open)
            ->exists();

        if ($existing) {
            throw new HttpException(409, 'An evacuation report is already open.');
        }

        $report = DB::transaction(function () use ($by): EvacuationReport {
            $report = EvacuationReport::query()->create([
                'status' => EvacuationStatus::Open,
                'triggered_at' => now(),
                'triggered_by' => $by->id,
            ]);

            $positions = WorkerPosition::query()
                ->where('is_on_site', true)
                ->get();

            foreach ($positions as $position) {
                EvacuationReportEntry::query()->create([
                    'evacuation_report_id' => $report->id,
                    'worker_id' => $position->worker_id,
                    'last_zone_id' => $position->zone_id,
                    'last_seen_at' => $position->last_seen_at,
                    'muster_status' => MusterStatus::Unaccounted,
                ]);
            }

            return $report;
        });

        $this->alerts->raise(
            type: AlertType::Evacuation,
            title: 'Evacuation triggered',
            payload: [
                'report_id' => $report->id,
                'entries' => $report->entries()->count(),
            ],
            audible: true,
            dedupeKey: "evacuation:{$report->id}",
        );

        broadcast(new EvacuationTriggered($report));

        return $report->load('entries');
    }

    public function accountManual(EvacuationReportEntry $entry, User $by): EvacuationReportEntry
    {
        if (! $entry->report?->isOpen()) {
            throw new HttpException(409, 'Evacuation report is closed.');
        }

        if ($entry->muster_status === MusterStatus::Accounted) {
            return $entry;
        }

        $entry->forceFill([
            'muster_status' => MusterStatus::Accounted,
            'accounted_at' => now(),
            'accounted_source' => AccountedSource::Manual,
            'accounted_by' => $by->id,
        ])->save();

        $fresh = $entry->fresh() ?? $entry;
        broadcast(new EvacuationEntryUpdated($fresh));

        return $fresh;
    }

    public function close(EvacuationReport $report, User $by, bool $force = false, ?string $note = null): EvacuationReport
    {
        if (! $report->isOpen()) {
            return $report;
        }

        $unaccounted = $report->entries()
            ->where('muster_status', MusterStatus::Unaccounted)
            ->count();

        if ($unaccounted > 0 && ! $force) {
            throw new HttpException(409, 'Cannot close while workers remain unaccounted; use force close with a note.');
        }

        if ($force && ($note === null || strlen($note) < 10)) {
            throw new HttpException(422, 'Force close requires a note of at least 10 characters.');
        }

        $report->forceFill([
            'status' => EvacuationStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $by->id,
            'force_closed' => $force && $unaccounted > 0,
            'close_note' => $note,
        ])->save();

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => $by->id,
            'route' => request()->path(),
            'payload' => [
                'target' => 'evacuation_close',
                'report_id' => $report->id,
                'force' => $force,
                'unaccounted' => $unaccounted,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $report->fresh() ?? $report;
    }

    public function downloadPdf(EvacuationReport $report): Response
    {
        $report->load(['entries.worker', 'entries.lastZone', 'triggerer']);

        $pdf = Pdf::loadView('pdf.evacuation-report', [
            'report' => $report,
        ]);

        return $pdf->download("evacuation-{$report->id}.pdf");
    }
}
