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
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\Backup\ArchiveEncryptor;
use App\Services\Backup\BackupService;
use App\Services\Backup\ExportAllService;
use App\Services\Backup\ExportManifestService;
use App\Services\Backup\SecureWipeService;
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
    Storage::fake('exports');
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

it('creates an encrypted backup archive and rotates to keep_count', function () {
    app(SettingsService::class)->set('backup.keep_count', 2);
    $service = app(BackupService::class);

    $first = $service->run();
    expect(Storage::disk('backups')->exists($first['path']))->toBeTrue();

    $encryptor = app(ArchiveEncryptor::class);
    $local = storage_path('app/tmp/test-backup.ir4bak');
    @mkdir(dirname($local), 0700, true);
    file_put_contents($local, Storage::disk('backups')->get($first['path']));
    $out = storage_path('app/tmp/test-backup.zip');
    $encryptor->decryptFile($local, $out, $encryptor->resolveKey(), ArchiveEncryptor::MAGIC_BACKUP);
    expect(filesize($out))->toBeGreaterThan(0);

    $service->run();
    $service->run();

    $files = collect(Storage::disk('backups')->files('daily'))
        ->filter(fn (string $path): bool => str_ends_with($path, '.ir4bak'));

    expect($files)->toHaveCount(2);
});

it('exports a handover archive with marker and refuses wipe without confirm/export', function () {
    User::factory()->withRole('Super Admin')->create();
    Storage::disk('private')->put('snapshots/demo.jpg', 'jpeg');

    $export = app(ExportAllService::class)->run('test-client-key');
    expect(Storage::disk('exports')->exists($export['archive_path']))->toBeTrue();

    $marker = app(ExportManifestService::class)->latest();
    expect($marker)->not->toBeNull()
        ->and($marker['archive_sha256'] ?? null)->not->toBeEmpty();

    $wipe = app(SecureWipeService::class);

    expect(fn () => $wipe->wipe('wrong'))
        ->toThrow(RuntimeException::class);

    // Wipe without a marker should fail after we delete markers.
    Storage::disk('exports')->deleteDirectory('final/markers');
    expect(fn () => $wipe->wipe(SecureWipeService::CONFIRM_PHRASE))
        ->toThrow(RuntimeException::class);
});

it('secure wipe succeeds after verified export and writes a receipt', function () {
    User::factory()->withRole('Super Admin')->create();
    Storage::disk('private')->put('snapshots/demo.jpg', 'jpeg');

    $export = app(ExportAllService::class)->run('test-client-key');
    expect(Storage::disk('private')->exists('snapshots/demo.jpg'))->toBeTrue();

    $result = app(SecureWipeService::class)->wipe(
        SecureWipeService::CONFIRM_PHRASE,
        $export['export_id'],
    );

    expect(Storage::disk('exports')->exists($result['receipt']))->toBeTrue()
        ->and(Storage::disk('private')->exists('snapshots/demo.jpg'))->toBeFalse()
        ->and(Storage::disk('exports')->exists($export['archive_path']))->toBeTrue();
});
