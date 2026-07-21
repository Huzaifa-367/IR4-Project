<?php

namespace App\Http\Controllers\Web\Reports;

use App\Enums\AuditEvent;
use App\Enums\ReportStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Reports\GenerateWeeklyReportRequest;
use App\Http\Requests\Web\Reports\UpdateReportSettingsRequest;
use App\Models\WeeklyReport;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Services\WeeklyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class WeeklyReportController extends BaseController
{
    public function index(Request $request, WeeklyReportService $reports): InertiaResponse
    {
        $this->authorize('viewAny', WeeklyReport::class);

        $query = WeeklyReport::query()->with(['generator', 'publisher', 'supersedes']);

        if ($request->user()?->primaryRole()?->is_read_only) {
            $query->where('status', ReportStatus::Published);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['period_start', 'period_end', 'status', 'generated_at', 'published_at', 'created_at'],
            searchable: ['report_number'],
            defaultSort: 'period_start',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate(20)->withQueryString();

        return Inertia::render('reports/index', [
            'reports' => [
                'data' => collect($paginator->items())->map(fn (WeeklyReport $r) => $reports->toArray($r))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'status' => $request->string('status')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'statuses' => collect(ReportStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'canGenerate' => $request->user()?->can('create-reports') ?? false,
            'canPublish' => $request->user()?->can('update-reports') ?? false,
            'canManageSettings' => $request->user()?->can('update-settings') ?? false,
            'canLogVehicles' => $request->user()?->can('create-vehicle-violations') ?? false,
        ]);
    }

    public function show(WeeklyReport $report, WeeklyReportService $reports): InertiaResponse
    {
        $this->authorize('view', $report);

        return Inertia::render('reports/show', [
            'report' => $reports->toArray($report),
            'badges' => $reports->automationBadges(),
            'canPublish' => request()->user()?->can('update-reports') ?? false,
        ]);
    }

    public function generate(
        GenerateWeeklyReportRequest $request,
        WeeklyReportService $reports,
    ): RedirectResponse {
        $this->authorize('generate', WeeklyReport::class);

        $report = $reports->generate(
            start: Carbon::parse($request->string('period_start')->toString()),
            end: Carbon::parse($request->string('period_end')->toString()),
            by: $request->user(),
            auto: false,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Weekly report generated.',
        ]);

        return redirect()->route('reports.show', $report);
    }

    public function publish(WeeklyReport $report, WeeklyReportService $reports): RedirectResponse
    {
        $this->authorize('publish', $report);
        $user = request()->user();
        abort_unless($user !== null, 401);
        $reports->publish($report, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Report published.',
        ]);

        return redirect()->route('reports.show', $report);
    }

    public function download(
        Request $request,
        WeeklyReport $report,
        WeeklyReportService $reports,
        AuditService $audit,
    ): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $report);

        $format = $request->string('format', 'pdf')->toString();
        $result = $reports->downloadUrl($report, $format);
        $audit->record(
            AuditEvent::Exported,
            $report,
            "Exported weekly report as {$format}.",
            newValues: ['format' => $format],
            user: $request->user(),
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->away($result['url']);
    }

    public function settings(SettingsService $settings): InertiaResponse
    {
        abort_unless(request()->user()?->can('update-settings'), 403);

        return Inertia::render('settings/reports', [
            'settings' => [
                'generation_day' => (string) $settings->get('report.generation_day', 'sunday'),
                'generation_time' => (string) $settings->get('report.generation_time', '06:00'),
                'auto_publish' => (bool) $settings->get('report.auto_publish', false),
                'week_start' => (string) $settings->get('report.week_start', 'sunday'),
                'completeness_threshold_pct' => (int) $settings->get('report.completeness_threshold_pct', 20),
            ],
        ]);
    }

    public function updateSettings(UpdateReportSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validated();
        $settings->set('report.generation_day', $data['generation_day']);
        $settings->set('report.generation_time', $data['generation_time']);
        $settings->set('report.auto_publish', (bool) $data['auto_publish']);
        if (array_key_exists('week_start', $data)) {
            $settings->set('report.week_start', $data['week_start']);
        }
        $settings->set('report.completeness_threshold_pct', (int) $data['completeness_threshold_pct']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Report settings saved.',
        ]);

        return redirect()->route('settings.reports.edit');
    }
}
