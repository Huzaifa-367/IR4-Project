<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Alert;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\GasReading;
use App\Models\TagReading;
use App\Models\WeeklyReport;
use App\Services\Backup\BackupPublisher;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreService;
use App\Services\RetentionService;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

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

it('stages a timestamped server+DB zip and publishes with rotation', function () {
    $fixture = storage_path('app/tmp/backup-fixture-'.uniqid());
    mkdir($fixture.'/app', 0700, true);
    mkdir($fixture.'/node_modules/left-pad', 0700, true);
    file_put_contents($fixture.'/app/demo.php', '<?php // fixture');
    file_put_contents($fixture.'/node_modules/left-pad/index.js', 'module.exports=1');
    $staging = storage_path('app/tmp/backup-staging-'.uniqid());
    config([
        'backup.app_root' => $fixture,
        'backup.staging_root' => $staging,
        'backup.disk_root' => Storage::disk('backups')->path(''),
    ]);

    app(SettingsService::class)->set('backup.keep_count', 2);
    $service = app(BackupService::class);

    $first = $service->run();
    expect(is_file($first['path']))->toBeTrue()
        ->and($first['path'])->toEndWith('.zip')
        ->and($first['sha256'])->not->toBeEmpty();

    $local = storage_path('app/tmp/test-site-backup.zip');
    @mkdir(dirname($local), 0700, true);
    file_put_contents($local, (string) file_get_contents($first['path']));

    $zip = new ZipArchive;
    expect($zip->open($local))->toBeTrue();
    expect($zip->locateName('DB/database.sql'))->not->toBeFalse()
        ->and($zip->locateName('manifest.json'))->not->toBeFalse()
        ->and($zip->locateName('server/app/demo.php'))->not->toBeFalse()
        ->and($zip->locateName('server/node_modules/left-pad/index.js'))->toBeFalse();
    $zip->close();

    $service->run();
    $service->run();
    app(BackupPublisher::class)->publishAll();

    $files = glob(Storage::disk('backups')->path('daily/ir4-backup-*.zip')) ?: [];

    expect($files)->toHaveCount(2)
        ->and(glob($staging.'/ir4-backup-*.zip') ?: [])->toBe([]);
});

it('verifies a site backup zip via RestoreService', function () {
    $fixture = storage_path('app/tmp/backup-fixture-'.uniqid());
    mkdir($fixture.'/app', 0700, true);
    file_put_contents($fixture.'/app/demo.php', '<?php');
    config([
        'backup.app_root' => $fixture,
        'backup.staging_root' => storage_path('app/tmp/backup-staging-'.uniqid()),
    ]);

    $path = app(BackupService::class)->run()['path'];
    $result = app(RestoreService::class)->verify($path);

    expect($result['meta']['format'] ?? null)->toBe('ir4-site-backup/v1')
        ->and($result['files'])->toContain('DB/database.sql')
        ->and($result['files'])->toContain('manifest.json');
});

it('prepares a volume backup in the shared restore inbox', function () {
    $fixture = storage_path('app/tmp/backup-fixture-'.uniqid());
    $staging = storage_path('app/tmp/backup-staging-'.uniqid());
    $volume = storage_path('app/tmp/backup-volume-'.uniqid());
    $inbox = storage_path('app/tmp/restore-inbox-'.uniqid());
    mkdir($fixture.'/app', 0700, true);
    mkdir($volume.'/daily', 0700, true);
    file_put_contents($fixture.'/app/demo.php', '<?php');
    config([
        'backup.app_root' => $fixture,
        'backup.staging_root' => $staging,
        'backup.disk_root' => $volume,
        'backup.restore_inbox' => $inbox,
    ]);

    $staged = app(BackupService::class)->run()['path'];
    $volumeArchive = $volume.'/daily/'.basename($staged);
    copy($staged, $volumeArchive);

    $prepared = app(RestoreService::class)->prepare('daily/'.basename($staged));

    expect($prepared)->toBe($inbox.'/'.basename($staged))
        ->and(is_file($prepared))->toBeTrue()
        ->and(hash_file('sha256', $prepared))->toBe(hash_file('sha256', $volumeArchive));
});
