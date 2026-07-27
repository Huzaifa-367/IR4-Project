<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Jobs\PruneRawSensorData;
use App\Models\Alert;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\GasReading;
use App\Models\TagReading;
use App\Models\WeeklyReport;
use App\Services\RetentionService;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('private');
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

it('runs the prune job for aged raw readings', function () {
    $reading = TagReading::factory()->create(['recorded_at' => now()->subDays(120)]);
    app(PruneRawSensorData::class)->handle(app(RetentionService::class));
    expect(TagReading::query()->whereKey($reading->id)->exists())->toBeFalse();
});
