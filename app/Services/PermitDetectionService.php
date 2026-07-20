<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\PermitTypeConflict;
use App\Models\WorkerPosition;
use App\Models\Zone;
use Illuminate\Support\Collection;

final class PermitDetectionService
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    /**
     * @return array{work_without_permit: int, simops: int, fire_watch: int}
     */
    public function run(): array
    {
        return [
            'work_without_permit' => $this->detectWorkWithoutPermit(),
            'simops' => $this->detectSimopsConflicts(),
            'fire_watch' => $this->detectHotWorkFireWatch(),
        ];
    }

    private function detectWorkWithoutPermit(): int
    {
        $raised = 0;

        $zoneIds = Zone::query()
            ->where('requires_permit', true)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($zoneIds as $zoneId) {
            /** @var Collection<int, int> $workersInZone */
            $workersInZone = WorkerPosition::query()
                ->where('zone_id', $zoneId)
                ->where('is_on_site', true)
                ->pluck('worker_id');

            if ($workersInZone->isEmpty()) {
                continue;
            }

            $coveredWorkers = Permit::query()
                ->where('status', PermitStatus::Active)
                ->where('zone_id', $zoneId)
                ->with('personnel')
                ->get()
                ->flatMap(fn (Permit $permit) => $permit->personnel->pluck('worker_id'))
                ->unique();

            foreach ($workersInZone->diff($coveredWorkers) as $workerId) {
                $this->alerts->raise(
                    type: AlertType::System,
                    severity: AlertSeverity::Critical,
                    title: 'Work without permit',
                    payload: [
                        'kind' => 'work_without_permit',
                        'zone_id' => $zoneId,
                        'worker_id' => $workerId,
                        'suggested_action' => 'create_permit',
                    ],
                    dedupeKey: "ptw:no_permit:{$zoneId}:{$workerId}",
                );
                $raised++;
            }
        }

        return $raised;
    }

    private function detectSimopsConflicts(): int
    {
        $raised = 0;

        $activeByZone = Permit::query()
            ->where('status', PermitStatus::Active)
            ->whereNotNull('zone_id')
            ->with(['type:id,code,name', 'zone:id,name'])
            ->get()
            ->groupBy('zone_id');

        foreach ($activeByZone as $zoneId => $permits) {
            $typeIds = $permits->pluck('permit_type_id')->unique()->values();

            if ($typeIds->count() < 2) {
                continue;
            }

            $conflicts = PermitTypeConflict::query()
                ->where('scope', 'same_zone')
                ->whereIn('permit_type_id', $typeIds)
                ->whereIn('conflicts_with_type_id', $typeIds)
                ->with(['permitType:id,code,name', 'conflictsWithType:id,code,name'])
                ->get();

            foreach ($conflicts as $conflict) {
                $hasPrimary = $permits->contains('permit_type_id', $conflict->permit_type_id);
                $hasOther = $permits->contains('permit_type_id', $conflict->conflicts_with_type_id);

                if (! $hasPrimary || ! $hasOther) {
                    continue;
                }

                $severity = $conflict->severity === 'block'
                    ? AlertSeverity::Critical
                    : AlertSeverity::Warning;

                $this->alerts->raise(
                    type: AlertType::System,
                    severity: $severity,
                    title: 'SIMOPS permit conflict',
                    payload: [
                        'kind' => 'simops_conflict',
                        'zone_id' => (int) $zoneId,
                        'permit_type_id' => $conflict->permit_type_id,
                        'conflicts_with_type_id' => $conflict->conflicts_with_type_id,
                        'permit_type_code' => $conflict->permitType?->code,
                        'conflicts_with_code' => $conflict->conflictsWithType?->code,
                        'severity' => $conflict->severity,
                        'note' => $conflict->note,
                        'suggested_action' => 'review_permits',
                    ],
                    dedupeKey: "ptw:simops:{$zoneId}:{$conflict->permit_type_id}:{$conflict->conflicts_with_type_id}",
                );
                $raised++;
            }
        }

        return $raised;
    }

    private function detectHotWorkFireWatch(): int
    {
        $raised = 0;

        $hotWorkPermits = Permit::query()
            ->where('status', PermitStatus::Active)
            ->whereNotNull('zone_id')
            ->whereHas('type', fn ($query) => $query->where('code', 'hot_work'))
            ->with(['personnel', 'zone:id,name', 'type:id,code,name'])
            ->get();

        foreach ($hotWorkPermits as $permit) {
            $fireWatchWorkers = $permit->personnel
                ->where('role_code', 'fire_watch')
                ->pluck('worker_id');

            if ($fireWatchWorkers->isEmpty()) {
                $this->alerts->raise(
                    type: AlertType::System,
                    severity: AlertSeverity::Critical,
                    title: 'Hot work without fire watch assigned',
                    payload: [
                        'kind' => 'hot_work_fire_watch',
                        'permit_id' => $permit->id,
                        'permit_number' => $permit->permit_number,
                        'zone_id' => $permit->zone_id,
                        'suggested_action' => 'assign_fire_watch',
                    ],
                    source: $permit,
                    dedupeKey: "ptw:fire_watch:missing:{$permit->id}",
                );
                $raised++;

                continue;
            }

            $present = WorkerPosition::query()
                ->where('zone_id', $permit->zone_id)
                ->where('is_on_site', true)
                ->whereIn('worker_id', $fireWatchWorkers)
                ->exists();

            if (! $present) {
                $this->alerts->raise(
                    type: AlertType::System,
                    severity: AlertSeverity::Critical,
                    title: 'Hot work fire watch not present in zone',
                    payload: [
                        'kind' => 'hot_work_fire_watch',
                        'permit_id' => $permit->id,
                        'permit_number' => $permit->permit_number,
                        'zone_id' => $permit->zone_id,
                        'fire_watch_worker_ids' => $fireWatchWorkers->values()->all(),
                        'suggested_action' => 'assign_fire_watch',
                    ],
                    source: $permit,
                    dedupeKey: "ptw:fire_watch:absent:{$permit->id}",
                );
                $raised++;
            }
        }

        return $raised;
    }
}
