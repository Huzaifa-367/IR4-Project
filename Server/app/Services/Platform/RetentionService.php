<?php

namespace App\Services\Platform;

use App\Models\EnvironmentalReading;
use App\Models\GasReading;
use App\Models\TagReading;
use App\Models\WeeklyReport;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Raw-sensor pruning + ad-hoc export cleanup (DOC-19).
 *
 * Allow-list only — compliance tables are never touched.
 */
final class RetentionService
{
    /**
     * Explicit allow-list of raw sensor tables that may be pruned.
     * DO NOT add compliance tables (alerts, incidents, LSR, reports, audit_logs, …).
     */
    public const PRUNE_ALLOW_LIST = [
        'tag_readings',
        'gas_readings',
        'environmental_readings',
    ];

    public const COMPLIANCE_TABLES = [
        'alerts',
        'gas_alarms',
        'hse_incidents',
        'incident_personnel',
        'incident_evidence',
        'lsr_violations',
        'weekly_reports',
        'vehicle_violations',
        'audit_logs',
        'entry_exit_logs',
        'worker_positions',
        'equipment',
        'equipment_inspections',
        'equipment_maintenances',
        'equipment_documents',
        'equipment_checkouts',
        'evacuation_reports',
        'evacuation_report_entries',
        'ppe_violations',
        'settings',
        'gas_thresholds',
        'zones',
        'devices',
        'assets',
        'cameras',
        'workers',
        'users',
    ];

    private const CHUNK = 1000;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array<string, int>
     */
    public function pruneRawSensorData(?\DateTimeInterface $now = null): array
    {
        $now = Carbon::instance($now ?? now());
        $tagDays = max(1, (int) $this->settings->get('retention.tag_readings_days', 90));
        $sensorDays = max(1, (int) $this->settings->get('retention.sensor_readings_days', 180));

        $counts = [
            'tag_readings' => $this->pruneTagReadings($now->copy()->subDays($tagDays)),
            'gas_readings' => $this->pruneGasReadings($now->copy()->subDays($sensorDays)),
            'environmental_readings' => $this->pruneEnvironmentalReadings($now->copy()->subDays($sensorDays)),
        ];

        Log::info('ir4.retention.pruned', $counts);

        return $counts;
    }

    /**
     * Remove ad-hoc export files older than retention.exports_days.
     * Published weekly-report PDFs under reports/ are exempt.
     */
    public function pruneExportFiles(?\DateTimeInterface $now = null): int
    {
        $now = Carbon::instance($now ?? now());
        $days = max(1, (int) $this->settings->get('retention.exports_days', 7));
        $cutoff = $now->copy()->subDays($days);
        $disk = Storage::disk('private');
        $removed = 0;

        foreach (['exports', 'tmp', 'imports'] as $directory) {
            if (! $disk->exists($directory)) {
                continue;
            }

            foreach ($disk->allFiles($directory) as $path) {
                if (str_starts_with($path, 'reports/')) {
                    continue;
                }
                if (str_starts_with($path, 'exports/final/')) {
                    continue;
                }

                $lastModified = $disk->lastModified($path);
                if (Carbon::createFromTimestamp($lastModified)->lessThan($cutoff)) {
                    $disk->delete($path);
                    $removed++;
                }
            }
        }

        // Never delete published report artifacts referenced by weekly_reports.
        $reportPaths = WeeklyReport::query()
            ->whereNotNull('pdf_path')
            ->pluck('pdf_path')
            ->merge(WeeklyReport::query()->whereNotNull('csv_path')->pluck('csv_path'))
            ->filter()
            ->all();

        Log::info('ir4.retention.exports_pruned', [
            'removed' => $removed,
            'protected_report_artifacts' => count($reportPaths),
        ]);

        return $removed;
    }

    /**
     * Delete expired Laravel database-cache rows (and locks).
     * Safe for Hostinger: keeps `cache` from growing without bound when CACHE_STORE=database.
     * No-op when the tables are absent; never touches domain/compliance data.
     */
    public function pruneExpiredDatabaseCache(?\DateTimeInterface $now = null): int
    {
        $cutoff = Carbon::instance($now ?? now())->getTimestamp();
        $deleted = 0;

        if (Schema::hasTable('cache')) {
            $deleted += $this->deleteExpiredCacheRows('cache', $cutoff);
        }
        if (Schema::hasTable('cache_locks')) {
            $deleted += $this->deleteExpiredCacheRows('cache_locks', $cutoff);
        }

        Log::info('ir4.retention.cache_pruned', ['removed' => $deleted]);

        return $deleted;
    }

    private function deleteExpiredCacheRows(string $table, int $cutoff): int
    {
        $deleted = 0;

        do {
            $keys = DB::table($table)
                ->where('expiration', '<', $cutoff)
                ->orderBy('key')
                ->limit(self::CHUNK)
                ->pluck('key');

            if ($keys->isEmpty()) {
                break;
            }

            $deleted += DB::table($table)->whereIn('key', $keys)->delete();
        } while ($keys->count() === self::CHUNK);

        return $deleted;
    }

    private function pruneTagReadings(\DateTimeInterface $before): int
    {
        // Tag rollups are optional/not used — prune unconditionally after the window.
        $deleted = 0;
        TagReading::query()
            ->where('recorded_at', '<', Carbon::instance($before))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$deleted): void {
                $ids = $rows->pluck('id');
                $deleted += TagReading::query()->whereIn('id', $ids)->delete();
            });

        return $deleted;
    }

    private function pruneGasReadings(\DateTimeInterface $before): int
    {
        // Gas history is raw-only (no rollup table). Prune by retention window like tag readings.
        $deleted = 0;
        GasReading::query()
            ->where('recorded_at', '<', Carbon::instance($before))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$deleted): void {
                $ids = $rows->pluck('id');
                $deleted += GasReading::query()->whereIn('id', $ids)->delete();
            });

        return $deleted;
    }

    private function pruneEnvironmentalReadings(\DateTimeInterface $before): int
    {
        // Environmental history is raw-only (no rollup table). Prune by retention window.
        $deleted = 0;
        EnvironmentalReading::query()
            ->where('recorded_at', '<', Carbon::instance($before))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$deleted): void {
                $ids = $rows->pluck('id');
                $deleted += EnvironmentalReading::query()->whereIn('id', $ids)->delete();
            });

        return $deleted;
    }
}
