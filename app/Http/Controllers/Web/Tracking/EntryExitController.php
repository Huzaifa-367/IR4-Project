<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Enums\AuditEvent;
use App\Enums\Direction;
use App\Http\Controllers\Web\BaseController;
use App\Models\EntryExitLog;
use App\Models\Worker;
use App\Services\AuditService;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EntryExitController extends BaseController
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->can('view-entry-exit'), 403);

        $query = EntryExitLog::query()->with(['worker', 'gateZone']);

        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction')->toString());
        }
        if ($request->filled('source')) {
            $query->where('source', $request->string('source')->toString());
        }
        if ($request->filled('worker_id')) {
            $query->where('worker_id', $request->integer('worker_id'));
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['occurred_at', 'direction', 'source'],
            searchable: [],
            defaultSort: 'occurred_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('tracking/entry-exit/index', [
            'logs' => [
                'data' => $paginator->getCollection()->map(fn (EntryExitLog $log) => [
                    'id' => $log->id,
                    'worker_id' => $log->worker_id,
                    'worker_name' => $log->worker?->name,
                    'direction' => $log->direction->value,
                    'source' => $log->source->value,
                    'occurred_at' => $log->occurred_at->toIso8601String(),
                    'correction_note' => $log->correction_note,
                    'gate_zone' => $log->gateZone?->name,
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'direction' => $request->string('direction')->toString(),
                'source' => $request->string('source')->toString(),
                'worker_id' => $request->string('worker_id')->toString(),
            ],
            'workers' => Worker::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canCorrect' => $request->user()?->can('manage-workers') ?? false,
        ]);
    }

    public function correct(Request $request, TrackingService $tracking): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-workers'), 403);

        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'direction' => ['required', 'in:in,out'],
            'occurred_at' => ['required', 'date'],
            'note' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $tracking->correctEntryExit(
            Worker::query()->findOrFail($data['worker_id']),
            Direction::from($data['direction']),
            $data['occurred_at'],
            $data['note'],
            $request->user(),
        );

        return redirect()->back();
    }

    public function export(Request $request, AuditService $audit): Response
    {
        abort_unless($request->user()?->can('view-entry-exit'), 403);

        $rows = EntryExitLog::query()
            ->with('worker')
            ->orderByDesc('occurred_at')
            ->limit(5000)
            ->get();
        $audit->record(
            AuditEvent::Exported,
            description: 'Exported entry and exit log.',
            newValues: ['row_count' => $rows->count()],
            user: $request->user(),
        );

        $csv = "id,worker,direction,source,occurred_at,note\n";
        foreach ($rows as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s\n",
                $log->id,
                str_replace(',', ' ', $log->worker?->name ?? ''),
                $log->direction->value,
                $log->source->value,
                $log->occurred_at->toIso8601String(),
                str_replace(["\n", ','], [' ', ' '], $log->correction_note ?? ''),
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="entry-exit.csv"',
        ]);
    }
}
