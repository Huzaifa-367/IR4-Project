<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Jobs\PruneExpiredCache;
use App\Jobs\PruneRawSensorData;
use App\Models\Alert;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\GasReading;
use App\Models\TagReading;
use App\Models\WeeklyReport;
use App\Services\BackupStatusService;
use App\Services\RetentionService;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('private');
    Storage::fake('backups');
});

it('prunes only allow-listed raw tables by age and never compliance tables', function () {
    $device = Device::factory()->create([
        'device_type' => DeviceType::GasDetector,
        'status' => HardwareStatus::Online,
    ]);
    $envDevice = Device::factory()->create([
        'device_type' => DeviceType::EnvironmentalSensor,
        'status' => HardwareStatus::Online,
    ]);

    app(SettingsService::class)->set('retention.tag_readings_days', 7, confirmed: true);
    app(SettingsService::class)->set('retention.sensor_readings_days', 7, confirmed: true);

    $old = now()->subDays(40)->startOfHour();

    TagReading::factory()->create(['recorded_at' => $old->copy()->addMinutes(5)]);
    TagReading::factory()->create(['recorded_at' => now()->subHour()]);

    $gas = GasReading::factory()->create([
        'device_id' => $device->id,
        'recorded_at' => $old->copy()->addMinutes(5),
        'lel_pct' => 1,
    ]);

    EnvironmentalReading::factory()->create([
        'device_id' => $envDevice->id,
        'recorded_at' => $old->copy()->addMinutes(5),
        'temperature_c' => 30,
    ]);
    $alert = Alert::factory()->create([
        'alert_type' => AlertType::System,
        'severity' => AlertSeverity::Warning,
        'title' => 'keep me',
        'status' => AlertStatus::Open,
        'raised_at' => $old,
    ]);

    $counts = app(RetentionService::class)->pruneRawSensorData();

    expect($counts['tag_readings'])->toBe(1)
        ->and($counts['gas_readings'])->toBe(1)
        ->and($counts['environmental_readings'])->toBe(1)
        ->and(TagReading::query()->count())->toBe(1)
        ->and(GasReading::query()->whereKey($gas->id)->exists())->toBeFalse()
        ->and(Alert::query()->whereKey($alert->id)->exists())->toBeTrue()
        ->and(array_intersect(RetentionService::PRUNE_ALLOW_LIST, RetentionService::COMPLIANCE_TABLES))->toBe([]);
});

it('prunes gas readings by age without requiring a rollup', function () {
    $device = Device::factory()->create([
        'device_type' => DeviceType::GasDetector,
        'status' => HardwareStatus::Online,
    ]);
    app(SettingsService::class)->set('retention.sensor_readings_days', 7, confirmed: true);

    GasReading::factory()->create([
        'device_id' => $device->id,
        'recorded_at' => now()->subDays(40),
        'lel_pct' => 1,
    ]);

    $counts = app(RetentionService::class)->pruneRawSensorData();

    expect($counts['gas_readings'])->toBe(1)
        ->and(GasReading::query()->count())->toBe(0);
});

it('prunes environmental readings by age without requiring a rollup', function () {
    $device = Device::factory()->create([
        'device_type' => DeviceType::EnvironmentalSensor,
        'status' => HardwareStatus::Online,
    ]);
    app(SettingsService::class)->set('retention.sensor_readings_days', 7, confirmed: true);

    EnvironmentalReading::factory()->create([
        'device_id' => $device->id,
        'recorded_at' => now()->subDays(40),
        'temperature_c' => 22,
    ]);

    $counts = app(RetentionService::class)->pruneRawSensorData();

    expect($counts['environmental_readings'])->toBe(1)
        ->and(EnvironmentalReading::query()->count())->toBe(0);
});

it('removes ad-hoc exports but keeps weekly report PDFs', function () {
    Storage::disk('private')->put('exports/tmp/old.csv', 'a');
    Storage::disk('private')->put('reports/1/report.pdf', 'pdf');
    touch(Storage::disk('private')->path('exports/tmp/old.csv'), now()->subDays(30)->getTimestamp());
    touch(Storage::disk('private')->path('reports/1/report.pdf'), now()->subDays(30)->getTimestamp());

    WeeklyReport::factory()->create([
        'pdf_path' => 'reports/1/report.pdf',
        'csv_path' => 'reports/1/report-csvs.zip',
    ]);

    app(SettingsService::class)->set('retention.exports_days', 7);

    $removed = app(RetentionService::class)->pruneExportFiles();

    expect($removed)->toBeGreaterThan(0)
        ->and(Storage::disk('private')->exists('exports/tmp/old.csv'))->toBeFalse()
        ->and(Storage::disk('private')->exists('reports/1/report.pdf'))->toBeTrue();
});

it('prunes expired database cache and lock rows only', function () {
    $past = now()->subHour()->getTimestamp();
    $future = now()->addHour()->getTimestamp();

    DB::table('cache')->insert([
        ['key' => 'ir4-test-expired', 'value' => 'old', 'expiration' => $past],
        ['key' => 'ir4-test-live', 'value' => 'live', 'expiration' => $future],
    ]);
    DB::table('cache_locks')->insert([
        ['key' => 'ir4-lock-expired', 'owner' => 't', 'expiration' => $past],
        ['key' => 'ir4-lock-live', 'owner' => 't', 'expiration' => $future],
    ]);

    $removed = app(RetentionService::class)->pruneExpiredDatabaseCache();
    app(PruneExpiredCache::class)->handle(app(RetentionService::class));

    expect($removed)->toBe(2)
        ->and(DB::table('cache')->where('key', 'ir4-test-expired')->exists())->toBeFalse()
        ->and(DB::table('cache')->where('key', 'ir4-test-live')->exists())->toBeTrue()
        ->and(DB::table('cache_locks')->where('key', 'ir4-lock-expired')->exists())->toBeFalse()
        ->and(DB::table('cache_locks')->where('key', 'ir4-lock-live')->exists())->toBeTrue();
});

it('configures encrypted Spatie backups for MySQL on the backups disk without mail', function () {
    expect(config('backup.backup.source.databases'))->toBe(['mysql'])
        ->and(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('backup.backup.encryption'))->toBe('aes256')
        ->and(config('backup.backup.verify_backup'))->toBeTrue()
        ->and(config('backup.cleanup.default_strategy.keep_daily_backups_for_days'))->toBe(30)
        ->and(config('backup.notifications.notifications.'.BackupHasFailedNotification::class))->toBe([])
        ->and(config('database.connections'))->toHaveKey('mysql')
        ->and(config('database.connections'))->toHaveCount(1);
});

it('blocks raw pruning until the current day has a successful backup', function () {
    Cache::forget('ir4:backup:last-success');
    $reading = TagReading::factory()->create(['recorded_at' => now()->subDays(120)]);
    app(PruneRawSensorData::class)->handle(
        app(BackupStatusService::class),
        app(RetentionService::class),
    );
    expect(TagReading::query()->whereKey($reading->id)->exists())->toBeTrue()
        ->and(Alert::query()->where('dedupe_key', 'backup:prune-blocked')->exists())->toBeTrue();

    Storage::disk('backups')->put('IR4/ir4-test.zip', 'encrypted-backup-fixture');
    app(BackupStatusService::class)->recordSuccess(new BackupWasSuccessful('backups', 'IR4'));
    app(PruneRawSensorData::class)->handle(
        app(BackupStatusService::class),
        app(RetentionService::class),
    );
    expect(TagReading::query()->whereKey($reading->id)->exists())->toBeFalse();
});

it('routes Spatie backup events through AlertService', function () {
    event(new BackupHasFailed(
        new RuntimeException('mysqldump failed'),
        'backups',
        'IR4',
    ));
    event(new UnhealthyBackupWasFound(
        'backups',
        'IR4',
        collect([[
            'check' => 'MaximumAgeInDays',
            'message' => 'The latest backup is too old.',
        ]]),
    ));
    event(new CleanupHasFailed(
        new RuntimeException('cleanup failed'),
        'backups',
        'IR4',
    ));

    expect(Alert::query()->where('dedupe_key', 'backup:failed')->where('status', AlertStatus::Open)->exists())->toBeTrue()
        ->and(Alert::query()->where('dedupe_key', 'backup:missing')->where('status', AlertStatus::Open)->exists())->toBeTrue()
        ->and(Alert::query()->where('dedupe_key', 'backup:cleanup-failed')->where('status', AlertStatus::Open)->exists())->toBeTrue();

    Storage::disk('backups')->put('IR4/ir4-test.zip', 'encrypted-backup-fixture');
    event(new BackupWasSuccessful('backups', 'IR4'));
    event(new HealthyBackupWasFound('backups', 'IR4'));
    event(new CleanupWasSuccessful('backups', 'IR4'));

    expect(Alert::query()->where('dedupe_key', 'backup:failed')->where('status', AlertStatus::Resolved)->exists())->toBeTrue()
        ->and(Alert::query()->where('dedupe_key', 'backup:missing')->where('status', AlertStatus::Resolved)->exists())->toBeTrue()
        ->and(Alert::query()->where('dedupe_key', 'backup:cleanup-failed')->where('status', AlertStatus::Resolved)->exists())->toBeTrue();
});
