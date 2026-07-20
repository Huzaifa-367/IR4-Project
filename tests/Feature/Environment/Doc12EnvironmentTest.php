<?php

use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Events\EnvironmentUpdated;
use App\Models\Alert;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\EnvironmentalRollup;
use App\Models\IngestEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function environmentHeaders(string $token): array
{
    return ['X-Device-Token' => $token];
}

/**
 * @param  array<string, mixed>  $metrics
 * @return array<string, mixed>
 */
function environmentEvent(
    array $metrics,
    ?string $uid = null,
    ?string $recordedAt = null,
    ?string $deviceReference = null,
): array {
    return array_filter([
        'event_uid' => $uid ?? (string) Str::uuid(),
        'device_ref' => $deviceReference,
        'recorded_at' => $recordedAt ?? now()->toIso8601String(),
        ...$metrics,
    ], fn (mixed $value): bool => $value !== null);
}

it('ingests present metrics and open extra parameters', function () {
    Event::fake([EnvironmentUpdated::class]);
    Cache::flush();
    $token = 'environment-live';
    $device = Device::factory()->withPlainToken($token)->create([
        'device_type' => DeviceType::EnvironmentalSensor,
    ]);

    $this->postJson(route('api.ingest.environmental-readings'), [
        'events' => [environmentEvent([
            'temperature_c' => 31.4,
            'extra' => ['pm25' => 18.2],
        ], deviceReference: $device->reference)],
    ], environmentHeaders($token))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $reading = EnvironmentalReading::query()->firstOrFail();
    expect($reading->asset_id)->toBe($device->asset_id)
        ->and($reading->temperature_c)->toBe('31.40')
        ->and($reading->humidity_pct)->toBeNull()
        ->and($reading->extra)->toBe(['pm25' => 18.2])
        ->and(IngestEvent::query()->where('stream', 'environmental_readings')->count())->toBe(0)
        ->and(Alert::query()->whereIn('alert_type', [AlertType::GasWarning, AlertType::GasAlarm])->count())->toBe(0);

    Event::assertDispatched(EnvironmentUpdated::class);
});

it('is idempotent and rejects unknown device references', function () {
    $token = 'environment-idempotent';
    $device = Device::factory()->withPlainToken($token)->create([
        'device_type' => DeviceType::EnvironmentalSensor,
    ]);
    $uid = (string) Str::uuid();
    $event = environmentEvent(['humidity_pct' => 60], $uid, deviceReference: $device->reference);

    $this->postJson(route('api.ingest.environmental-readings'), [
        'events' => [$event],
    ], environmentHeaders($token))->assertAccepted()->assertJsonPath('accepted', 1);
    $this->postJson(route('api.ingest.environmental-readings'), [
        'events' => [$event],
    ], environmentHeaders($token))->assertAccepted()->assertJsonPath('duplicates', 1);
    $this->postJson(route('api.ingest.environmental-readings'), [
        'events' => [environmentEvent(['humidity_pct' => 60], deviceReference: 'missing-sensor')],
    ], environmentHeaders($token))
        ->assertAccepted()
        ->assertJsonPath('rejected.0.code', 'UNKNOWN_REFERENCE');

    expect(EnvironmentalReading::query()->count())->toBe(1);
});

it('stores and rolls up backfill without broadcasting', function () {
    Event::fake([EnvironmentUpdated::class]);
    Cache::flush();
    $token = 'environment-backfill';
    $device = Device::factory()->withPlainToken($token)->create([
        'device_type' => DeviceType::EnvironmentalSensor,
    ]);
    $recordedAt = now()->subHours(2)->startOfHour()->addMinutes(10);

    foreach ([[20, 40, 2, 10], [30, 60, 4, 30]] as [$temperature, $humidity, $wind, $pm25]) {
        $this->postJson(route('api.ingest.environmental-readings'), [
            'events' => [environmentEvent([
                'temperature_c' => $temperature,
                'humidity_pct' => $humidity,
                'wind_speed_ms' => $wind,
                'extra' => ['pm25' => $pm25],
            ], recordedAt: $recordedAt->toIso8601String(), deviceReference: $device->reference)],
        ], environmentHeaders($token))->assertAccepted();
    }

    $rollup = EnvironmentalRollup::query()->firstOrFail();
    expect(EnvironmentalReading::query()->where('is_backfill', true)->count())->toBe(2)
        ->and($rollup->temp_min)->toBe('20.00')
        ->and($rollup->temp_avg)->toBe('25.00')
        ->and($rollup->temp_max)->toBe('30.00')
        ->and($rollup->extra_stats['pm25'])->toBe(['min' => 10, 'avg' => 20, 'max' => 30]);

    Event::assertNotDispatched(EnvironmentUpdated::class);
});

it('returns latest sensor values and stale state', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->create([
        'device_type' => DeviceType::EnvironmentalSensor,
    ]);
    EnvironmentalReading::factory()->create([
        'device_id' => $device->id,
        'asset_id' => $device->asset_id,
        'recorded_at' => now()->subMinutes(20),
        'received_at' => now()->subMinutes(20),
        'extra' => ['pm10' => 11],
    ]);

    $this->actingAs($admin)
        ->getJson(route('environment.live'))
        ->assertOk()
        ->assertJsonPath('data.sensors.0.device_id', $device->id)
        ->assertJsonPath('data.sensors.0.is_stale', true)
        ->assertJsonPath('data.sensors.0.extra.pm10', 11);
});

it('uses raw data for a day and rollups beyond 24 hours including extra parameters', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $token = 'environment-trends';
    $device = Device::factory()->withPlainToken($token)->create([
        'device_type' => DeviceType::EnvironmentalSensor,
    ]);

    $this->postJson(route('api.ingest.environmental-readings'), [
        'events' => [environmentEvent(
            ['temperature_c' => 25, 'extra' => ['pm25' => 9]],
            recordedAt: now()->subDays(3)->toIso8601String(),
            deviceReference: $device->reference,
        )],
    ], environmentHeaders($token))->assertAccepted();

    $this->actingAs($admin)
        ->getJson(route('environment.trends', [
            'parameter' => 'pm25',
            'device_id' => $device->id,
            'range' => 'custom',
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('data.source', 'rollup')
        ->assertJsonPath('data.points.0.avg', 9);

    EnvironmentalReading::factory()->create([
        'device_id' => $device->id,
        'recorded_at' => now(),
        'received_at' => now(),
        'temperature_c' => 29,
    ]);
    $this->actingAs($admin)
        ->getJson(route('environment.trends', [
            'parameter' => 'temperature_c',
            'device_id' => $device->id,
            'range' => 'day',
        ]))
        ->assertOk()
        ->assertJsonPath('data.source', 'raw');
});

it('falls back to hourly raw aggregation when rollups are missing', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->create([
        'device_type' => DeviceType::EnvironmentalSensor,
    ]);
    $recordedAt = now()->subDays(3)->startOfHour()->addMinutes(15);

    EnvironmentalReading::factory()->create([
        'device_id' => $device->id,
        'asset_id' => $device->asset_id,
        'recorded_at' => $recordedAt,
        'received_at' => $recordedAt,
        'temperature_c' => 22,
        'humidity_pct' => 50,
        'wind_speed_ms' => 3,
    ]);
    EnvironmentalReading::factory()->create([
        'device_id' => $device->id,
        'asset_id' => $device->asset_id,
        'recorded_at' => $recordedAt->copy()->addMinutes(30),
        'received_at' => $recordedAt->copy()->addMinutes(30),
        'temperature_c' => 26,
        'humidity_pct' => 55,
        'wind_speed_ms' => 4,
    ]);

    expect(EnvironmentalRollup::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->getJson(route('environment.trends', [
            'parameter' => 'temperature_c',
            'device_id' => $device->id,
            'range' => 'week',
        ]))
        ->assertOk()
        ->assertJsonPath('data.source', 'raw-hourly')
        ->assertJsonPath('data.points.0.avg', 24);
});

it('gates all environmental reads with view-dashboard and exposes no write route', function () {
    $user = User::factory()->withRole('Field Staff')->create();
    $viewer = User::factory()->withRole('Project Manager')->create();

    $this->actingAs($user)->get(route('environment.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('environment.index'))->assertOk();

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'environment'))
        ->reject(fn ($route): bool => str_starts_with($route->uri(), 'api/ingest/'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->values()
        ->all();
    expect($routes)->not->toContain('POST')
        ->and($routes)->not->toContain('PUT')
        ->and($routes)->not->toContain('PATCH')
        ->and($routes)->not->toContain('DELETE');
});
