<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Throwable;

/**
 * Routes Spatie backup events into in-app system alerts (on-prem, no mail).
 */
final class BackupStatusService
{
    private const LAST_SUCCESS_KEY = 'ir4:backup:last-success';

    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    public function recordSuccess(BackupWasSuccessful $event): void
    {
        $backup = BackupDestination::create($event->diskName, $event->backupName)->newestBackup();
        $completedAt = CarbonImmutable::now();
        Cache::forever(self::LAST_SUCCESS_KEY, $completedAt->toIso8601String());
        Log::info('ir4.backup.completed', [
            'disk' => $event->diskName,
            'name' => $event->backupName,
            'path' => $backup?->path(),
            'size_bytes' => $backup?->sizeInBytes(),
            'completed_at' => $completedAt->toIso8601String(),
        ]);
        $this->resolveAlert('backup:failed');
        $this->resolveAlert('backup:missing');
        $this->resolveAlert('backup:prune-blocked');
    }

    public function recordFailure(BackupHasFailed $event): void
    {
        Log::error('ir4.backup.failed', [
            'disk' => $event->diskName,
            'name' => $event->backupName,
            'error' => $event->exception->getMessage(),
        ]);
        $this->raiseWarning(
            title: 'Site backup failed',
            payload: [
                'disk' => $event->diskName,
                'name' => $event->backupName,
                'error' => $event->exception->getMessage(),
            ],
            dedupeKey: 'backup:failed',
        );
    }

    public function recordUnhealthy(UnhealthyBackupWasFound $event): void
    {
        $messages = $event->failureMessages->pluck('message')->values()->all();
        Log::warning('ir4.backup.unhealthy', [
            'disk' => $event->diskName,
            'name' => $event->backupName,
            'messages' => $messages,
        ]);
        $this->raiseWarning(
            title: 'Site backup is unhealthy',
            payload: [
                'disk' => $event->diskName,
                'name' => $event->backupName,
                'messages' => $messages,
            ],
            dedupeKey: 'backup:missing',
        );
    }

    public function recordHealthy(HealthyBackupWasFound $event): void
    {
        Log::info('ir4.backup.healthy', [
            'disk' => $event->diskName,
            'name' => $event->backupName,
        ]);
        $this->resolveAlert('backup:missing');
    }

    public function recordCleanupFailure(CleanupHasFailed $event): void
    {
        Log::error('ir4.backup.cleanup_failed', [
            'disk' => $event->diskName,
            'name' => $event->backupName,
            'error' => $event->exception->getMessage(),
        ]);
        $this->raiseWarning(
            title: 'Backup cleanup failed',
            payload: [
                'disk' => $event->diskName,
                'name' => $event->backupName,
                'error' => $event->exception->getMessage(),
            ],
            dedupeKey: 'backup:cleanup-failed',
        );
    }

    public function recordCleanupSuccess(CleanupWasSuccessful $event): void
    {
        Log::info('ir4.backup.cleanup_completed', [
            'disk' => $event->diskName,
            'name' => $event->backupName,
        ]);
        $this->resolveAlert('backup:cleanup-failed');
    }

    public function canPrune(?\DateTimeInterface $now = null): bool
    {
        $current = CarbonImmutable::instance($now ?? now());
        $completedAt = Cache::get(self::LAST_SUCCESS_KEY);
        if (is_string($completedAt) && CarbonImmutable::parse($completedAt)->isSameDay($current)) {
            return true;
        }
        Log::warning('ir4.retention.skipped_without_current_backup', [
            'last_success' => $completedAt,
        ]);
        $this->raiseWarning(
            title: 'Retention pruning blocked',
            payload: [
                'reason' => 'No successful site backup exists for the current day.',
                'last_success' => $completedAt,
            ],
            dedupeKey: 'backup:prune-blocked',
        );

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function raiseWarning(string $title, array $payload, string $dedupeKey): void
    {
        try {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: $title,
                payload: $payload,
                dedupeKey: $dedupeKey,
            );
        } catch (Throwable $exception) {
            Log::error('ir4.backup.alert_failed', [
                'dedupe_key' => $dedupeKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveAlert(string $dedupeKey): void
    {
        try {
            $this->alerts->resolveByDedupeKey($dedupeKey);
        } catch (Throwable $exception) {
            Log::error('ir4.backup.alert_resolution_failed', [
                'dedupe_key' => $dedupeKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
