<?php

namespace App\Services\Backup;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Services\AlertService;
use Illuminate\Support\Facades\Storage;

final class DiskSpaceMonitor
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    public function check(): void
    {
        $threshold = max(1, (int) config('backup.disk_space_warn_pct', 15));

        foreach (['private', (string) config('backup.disk', 'backups')] as $diskName) {
            $root = Storage::disk($diskName)->path('');
            if (! is_dir($root)) {
                continue;
            }

            $total = @disk_total_space($root);
            $free = @disk_free_space($root);
            if ($total === false || $free === false || $total <= 0) {
                continue;
            }

            $freePct = (int) round(($free / $total) * 100);
            if ($freePct < $threshold) {
                $this->alerts->raise(
                    type: AlertType::System,
                    severity: AlertSeverity::Warning,
                    title: 'Disk space low',
                    payload: [
                        'disk' => $diskName,
                        'free_pct' => $freePct,
                        'threshold_pct' => $threshold,
                        'path' => $root,
                    ],
                    dedupeKey: "disk:low:{$diskName}",
                );
            }
        }
    }
}
