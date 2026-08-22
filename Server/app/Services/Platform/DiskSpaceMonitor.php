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
        try {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Disk space low',
                payload: [
                    'disk' => $diskName,
                    'root' => $root,
                    'free_pct' => $freePercentage,
                    'threshold_pct' => $threshold,
                ],
                dedupeKey: 'disk_space_low:'.$diskName,
            );
        } catch (Throwable $exception) {
            Log::error('ir4.disk_space.alert_failed', [
                'disk' => $diskName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveAlert(string $diskName): void
    {
        try {
            $this->alerts->resolveByDedupeKey('disk_space_low:'.$diskName);
        } catch (Throwable $exception) {
            Log::error('ir4.disk_space.alert_resolution_failed', [
                'disk' => $diskName,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
