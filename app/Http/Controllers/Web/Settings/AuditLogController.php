<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\AuditEvent;
use App\Http\Controllers\Web\BaseController;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditLogController extends BaseController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());
        $query = $this->filteredQuery($filters)->with('user:id,name,email');
        $paginator = $query->paginate(25)->withQueryString();

        return Inertia::render('settings/audit-log/index', [
            'auditLogs' => [
                'data' => collect($paginator->items())->map(fn (AuditLog $log): array => $this->serialize($log)),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => $filters,
            'events' => collect(AuditEvent::cases())->map(fn (AuditEvent $event): array => [
                'value' => $event->value,
                'label' => $event->label(),
            ]),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'models' => AuditLog::query()
                ->whereNotNull('auditable_type')
                ->distinct()
                ->orderBy('auditable_type')
                ->pluck('auditable_type')
                ->map(fn (string $type): array => ['value' => $type, 'label' => class_basename($type)]),
        ]);
    }

    public function export(Request $request, AuditService $audit): StreamedResponse
    {
        $filters = $request->validate($this->filterRules());
        $audit->record(
            AuditEvent::Exported,
            description: 'Exported audit log CSV.',
            newValues: ['filters' => $filters],
            user: $request->user(),
            route: 'settings.audit-log.export',
        );

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fputcsv($output, [
                'Occurred at', 'Event', 'User', 'Subject type', 'Subject ID',
                'Description', 'Old values', 'New values', 'IP address', 'Route', 'User agent',
            ]);
            $this->filteredQuery($filters)->with('user:id,name')->chunkById(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    fputcsv($output, [
                        $log->occurred_at->toIso8601String(),
                        $log->event->value,
                        $log->user === null ? 'System' : $log->user->name,
                        $log->auditable_type,
                        $log->auditable_id,
                        $log->description,
                        json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $log->ip_address,
                        $log->route,
                        $log->user_agent,
                    ]);
                }
            });
            fclose($output);
        }, 'audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('user_id', $userId))
            ->when($filters['auditable_type'] ?? null, fn (Builder $query, string $type) => $query->where('auditable_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('occurred_at', '<=', $to))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('description', 'like', "%{$search}%"))
            ->latest('occurred_at')
            ->latest('id');
    }

    /**
     * @return array<string, list<string>>
     */
    private function filterRules(): array
    {
        return [
            'event' => ['nullable', 'string', 'in:'.collect(AuditEvent::cases())->pluck('value')->implode(',')],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event->value,
            'user' => $log->user === null ? null : ['id' => $log->user->id, 'name' => $log->user->name],
            'auditable_type' => $log->auditable_type,
            'auditable_label' => $log->auditable_type === null ? null : class_basename($log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'description' => $log->description,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'route' => $log->route,
            'occurred_at' => $log->occurred_at->toIso8601String(),
        ];
    }
}
