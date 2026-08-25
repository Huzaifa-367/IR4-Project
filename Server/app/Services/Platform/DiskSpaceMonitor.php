<?php

namespace App\Services\Platform;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Services\Alert\AlertService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DiskSpaceMonitor
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly TechTeamNotifier $techTeam,
    ) {}

    public function check(): void
    {
        $threshold = max(1, (int) config('ir4.infrastructure.disk_space_warn_pct', 15));
        foreach (['private', 'backups'] as $diskName) {
            $this->checkDisk($diskName, $threshold);
        }
    }

    private function checkDisk(string $diskName, int $threshold): void
    {
        $root = config("filesystems.disks.{$diskName}.root");
        if (! is_string($root) || ! is_dir($root)) {
            return;
        }
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);
        if ($total === false || $free === false || $total <= 0) {
            return;
        }
        $freePercentage = (int) round(($free / $total) * 100);
        if ($freePercentage > $threshold) {
            $this->resolveAlert($diskName);

            return;
        }
        $payload = [
            'disk' => $diskName,
            'root' => $root,
            'free_pct' => $freePercentage,
            'threshold_pct' => $threshold,
        ];
        $dedupeKey = 'disk_space_low:'.$diskName;
        try {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Disk space low',
                payload: $payload,
                dedupeKey: $dedupeKey,
            );
        } catch (Throwable $exception) {
            Log::error('ir4.disk_space.alert_failed', [
                'disk' => $diskName,
                'error' => $exception->getMessage(),
            ]);
        }
        $this->techTeam->notify($dedupeKey, 'Disk space low', [
            'category' => 'Disk',
            'severity' => 'Storage risk',
            'summary' => "Filesystem \"{$diskName}\" is down to {$freePercentage}% free (threshold {$threshold}%). Snapshots, backups, or ingest may fail if space runs out.",
            'suggested_action' => $diskName === 'backups'
                ? 'Free space on the backup volume, verify backup:clean rotation, and confirm BACKUP_DISK_ROOT is on its own disk.'
                : 'Free space on the private data volume (snapshots/documents). Move or delete aged export files only from allow-listed paths.',
            'details' => [
                'Disk' => $diskName,
                'Root path' => $root,
                'Free' => $freePercentage.'%',
                'Warn below' => $threshold.'%',
                'Free bytes' => number_format((int) $free),
                'Total bytes' => number_format((int) $total),
            ],
        ]);
    }

    private function resolveAlert(string $diskName): void
    {
        $dedupeKey = 'disk_space_low:'.$diskName;
        try {
            $this->alerts->resolveByDedupeKey($dedupeKey);
        } catch (Throwable $exception) {
            Log::error('ir4.disk_space.alert_resolution_failed', [
                'disk' => $diskName,
                'error' => $exception->getMessage(),
            ]);
        }
        $this->techTeam->clear($dedupeKey);
    }
}
