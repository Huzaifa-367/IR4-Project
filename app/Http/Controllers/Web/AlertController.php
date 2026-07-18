<?php

namespace App\Http\Controllers\Web;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AlertController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Alert::class);

        $query = Alert::query();

        if ($request->filled('alert_type')) {
            $query->where('alert_type', $request->string('alert_type')->toString());
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        } else {
            $query->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value]);
        }

        if ($request->filled('date_from')) {
            $query->where('raised_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('raised_at', '<=', $request->date('date_to')->endOfDay());
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['raised_at', 'severity', 'status', 'alert_type'],
            searchable: ['title'],
            defaultSort: 'raised_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('alerts/index', [
            'alerts' => [
                'data' => AlertResource::collection($paginator->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'alert_type' => $request->string('alert_type')->toString(),
                'severity' => $request->string('severity')->toString(),
                'status' => $request->string('status')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'alertTypes' => collect(AlertType::cases())->map(fn (AlertType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'severities' => collect(AlertSeverity::cases())->map(fn (AlertSeverity $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'statuses' => collect(AlertStatus::cases())->map(fn (AlertStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'canAcknowledge' => $request->user()?->can('acknowledge-alerts') ?? false,
            'canResolve' => $request->user()?->can('configure-alerts') ?? false,
            'audibleEnabled' => (bool) app(\App\Services\SettingsService::class)->get('alert.audible_enabled', true),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $alerts = Alert::query()
            ->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value])
            ->orderByDesc('raised_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => AlertResource::collection($alerts)->resolve($request),
        ]);
    }

    public function acknowledge(Alert $alert, AlertService $alerts): RedirectResponse
    {
        $this->authorize('acknowledge', $alert);
        $alerts->acknowledge($alert, request()->user());

        return redirect()->back();
    }

    public function acknowledgeBulk(Request $request, AlertService $alerts): RedirectResponse
    {
        abort_unless($request->user()?->can('acknowledge-alerts'), 403);

        $data = $request->validate([
            'alert_ids' => ['required', 'array', 'min:1'],
            'alert_ids.*' => ['integer', 'exists:alerts,id'],
        ]);

        $open = Alert::query()
            ->whereIn('id', $data['alert_ids'])
            ->where('status', AlertStatus::Open->value)
            ->get();

        foreach ($open as $alert) {
            $alerts->acknowledge($alert, $request->user());
        }

        return redirect()->back();
    }

    public function resolve(Request $request, Alert $alert, AlertService $alerts): RedirectResponse
    {
        $this->authorize('resolve', $alert);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $alerts->resolve($alert, $data['note'] ?? null);

        return redirect()->back();
    }
}
