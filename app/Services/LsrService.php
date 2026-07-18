<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LsrService
{
    /**
     * Prefill only — never inserts a row.
     *
     * @return array<string, mixed>
     */
    public function prefillFromAlert(Alert $alert): array
    {
        $payload = $alert->payload ?? [];
        $ppeId = $payload['ppe_violation_id'] ?? null;
        $category = $this->categoryFromAlert($alert);

        // PPE-linked LSR never carries worker identity (DOC-10 / DOC-14).
        $workerId = $ppeId !== null
            ? null
            : ($payload['worker_id'] ?? null);

        return [
            'category' => $category->value,
            'occurred_at' => $payload['detected_at']
                ?? $payload['occurred_at']
                ?? optional($alert->raised_at)?->toIso8601String(),
            'worker_id' => $workerId,
            'zone_id' => $payload['zone_id'] ?? null,
            'camera_id' => $payload['camera_id'] ?? null,
            'alert_id' => $alert->id,
            'ppe_violation_id' => $ppeId,
            'description' => $alert->title,
            'alert' => [
                'id' => $alert->id,
                'alert_type' => $alert->alert_type->value,
                'title' => $alert->title,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $logger): LsrViolation
    {
        $ppeId = isset($data['ppe_violation_id']) ? (int) $data['ppe_violation_id'] : null;
        $workerId = isset($data['worker_id']) ? (int) $data['worker_id'] : null;

        if ($ppeId !== null) {
            PpeViolation::query()->findOrFail($ppeId);
            $workerId = null;
        }

        $lsr = LsrViolation::query()->create([
            'category' => LsrCategory::from((string) $data['category']),
            'occurred_at' => $data['occurred_at'],
            'worker_id' => $workerId,
            'zone_id' => $data['zone_id'] ?? null,
            'camera_id' => $data['camera_id'] ?? null,
            'alert_id' => $data['alert_id'] ?? null,
            'ppe_violation_id' => $ppeId,
            'description' => $data['description'] ?? null,
            'status' => LsrStatus::Open,
            'logged_by' => $logger->id,
        ]);

        $this->audit('config_changed', [
            'target' => 'lsr_create',
            'lsr_id' => $lsr->id,
            'category' => $lsr->category->value,
            'alert_id' => $lsr->alert_id,
            'ppe_violation_id' => $lsr->ppe_violation_id,
        ]);

        return $lsr->fresh(['worker', 'zone', 'alert', 'ppeViolation', 'logger']) ?? $lsr;
    }

    /**
     * @param  array{action_taken: string}  $data
     */
    public function close(LsrViolation $lsr, array $data, User $closer): LsrViolation
    {
        if ($lsr->status === LsrStatus::Closed) {
            return $lsr;
        }

        $action = trim((string) ($data['action_taken'] ?? ''));
        if (strlen($action) < 10) {
            throw ValidationException::withMessages([
                'action_taken' => ['Action taken is required (min 10 characters) to close an LSR.'],
            ]);
        }

        $lsr->forceFill([
            'action_taken' => $action,
            'status' => LsrStatus::Closed,
            'closed_by' => $closer->id,
            'closed_at' => now(),
        ])->save();

        $this->audit('config_changed', [
            'target' => 'lsr_close',
            'lsr_id' => $lsr->id,
        ]);

        return $lsr->fresh() ?? $lsr;
    }

    /**
     * @param  list<int>  $ids
     * @return list<LsrViolation>
     */
    public function closeBulk(array $ids, string $actionTaken, User $closer): array
    {
        $action = trim($actionTaken);
        if (strlen($action) < 10) {
            throw ValidationException::withMessages([
                'action_taken' => ['Action taken is required (min 10 characters).'],
            ]);
        }

        return DB::transaction(function () use ($ids, $action, $closer): array {
            $closed = [];
            foreach ($ids as $id) {
                $lsr = LsrViolation::query()->findOrFail($id);
                $closed[] = $this->close($lsr, ['action_taken' => $action], $closer);
            }

            return $closed;
        });
    }

    /**
     * @return array{open: int, by_category: list<array{category: string, label: string, open: int, closed: int, total: int}>}
     */
    public function summary(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = LsrViolation::query();
        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('occurred_at', '<=', $to);
        }

        $rows = $query->get(['category', 'status']);
        $byCategory = [];

        foreach (LsrCategory::cases() as $category) {
            $byCategory[$category->value] = [
                'category' => $category->value,
                'label' => $category->label(),
                'open' => 0,
                'closed' => 0,
                'total' => 0,
            ];
        }

        foreach ($rows as $row) {
            $key = $row->category->value;
            $byCategory[$key]['total']++;
            if ($row->status === LsrStatus::Open) {
                $byCategory[$key]['open']++;
            } else {
                $byCategory[$key]['closed']++;
            }
        }

        return [
            'open' => $rows->where('status', LsrStatus::Open)->count(),
            'by_category' => array_values($byCategory),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(LsrViolation $lsr, bool $canSeeIdentity = false): array
    {
        $lsr->loadMissing(['worker', 'zone', 'camera', 'alert', 'ppeViolation', 'logger', 'closer']);

        $worker = $lsr->worker;

        return [
            'id' => $lsr->id,
            'category' => $lsr->category->value,
            'category_label' => $lsr->category->label(),
            'occurred_at' => optional($lsr->occurred_at)?->toIso8601String(),
            'worker_id' => $lsr->worker_id,
            'worker_label' => $worker === null
                ? null
                : ($canSeeIdentity ? $worker->name : $worker->anonymizedLabel()),
            'zone_id' => $lsr->zone_id,
            'zone_name' => $lsr->zone?->name,
            'camera_id' => $lsr->camera_id,
            'alert_id' => $lsr->alert_id,
            'ppe_violation_id' => $lsr->ppe_violation_id,
            'description' => $lsr->description,
            'action_taken' => $lsr->action_taken,
            'status' => $lsr->status->value,
            'status_label' => $lsr->status->label(),
            'closed_at' => optional($lsr->closed_at)?->toIso8601String(),
            'closed_by_name' => $lsr->closer?->name,
            'logged_by_name' => $lsr->logger?->name,
            'created_at' => optional($lsr->created_at)?->toIso8601String(),
        ];
    }

    public function categoryFromAlert(Alert $alert): LsrCategory
    {
        return match ($alert->alert_type) {
            AlertType::PpeViolation, AlertType::HeightWithoutHarness => LsrCategory::MissingPpe,
            AlertType::RedZoneIntrusion => LsrCategory::RedZoneIntrusion,
            AlertType::UnauthorizedZoneAccess => LsrCategory::UnauthorizedZoneAccess,
            AlertType::ZoneOccupancyExceeded => LsrCategory::ZoneOccupancyExceeded,
            AlertType::WorkerDown => LsrCategory::WorkerDown,
            default => LsrCategory::MissingPpe,
        };
    }

    public function alertSuggestsLsr(Alert $alert): bool
    {
        return (($alert->payload['suggested_action'] ?? null) === 'log_lsr')
            || in_array($alert->alert_type, [
                AlertType::PpeViolation,
                AlertType::RedZoneIntrusion,
                AlertType::UnauthorizedZoneAccess,
                AlertType::ZoneOccupancyExceeded,
                AlertType::HeightWithoutHarness,
            ], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $eventType, array $payload): void
    {
        AuditLog::query()->create([
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => $payload,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
