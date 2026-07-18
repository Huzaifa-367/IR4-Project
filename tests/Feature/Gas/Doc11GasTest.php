<?php

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\GasAlarmLevel;
use App\Enums\GasType;
use App\Events\GasLiveUpdated;
use App\Models\Alert;
use App\Models\Device;
use App\Models\GasAlarm;
use App\Models\GasReading;
use App\Models\GasThreshold;
use App\Models\User;
use App\Services\GasMonitoringService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function gasIngestHeaders(string $plain): array
{
    return ['X-Device-Token' => $plain];
}

function gasEvent(array $channels, ?string $uid = null, ?string $recordedAt = null, ?string $deviceRef = null): array
{
    return array_filter([
        'event_uid' => $uid ?? (string) Str::uuid(),
        'device_ref' => $deviceRef,
        'recorded_at' => $recordedAt ?? now()->toIso8601String(),
        ...$channels,
    ], fn ($v) => $v !== null);
}

it('ingests readings populating only present channels with denormalized asset', function () {
    $plain = 'gas-ok';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 3.5], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted()->assertJsonPath('accepted', 1);

    $reading = GasReading::query()->first();
    expect($reading)->not->toBeNull()
        ->and($reading->h2s_ppm)->toBe('3.50')
        ->and($reading->lel_pct)->toBeNull()
        ->and($reading->asset_id)->toBe($device->asset_id)
        ->and(GasReading::query()->count())->toBe(1);
});

it('is idempotent on device_id and event_uid', function () {
    $plain = 'gas-idem';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();
    $uid = (string) Str::uuid();

    $payload = ['events' => [gasEvent(['lel_pct' => 2], $uid, deviceRef: $device->reference)]];
    $this->postJson(route('api.ingest.gas-readings'), $payload, gasIngestHeaders($plain))
        ->assertAccepted()->assertJsonPath('accepted', 1);
    $this->postJson(route('api.ingest.gas-readings'), $payload, gasIngestHeaders($plain))
        ->assertAccepted()->assertJsonPath('duplicates', 1);

    expect(GasReading::query()->count())->toBe(1);
});

it('evaluates live crossings into gas_alarm and alerts', function () {
    Event::fake([GasLiveUpdated::class]);

    $plain = 'gas-alarm';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 12], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->count())->toBe(1)
        ->and(GasAlarm::query()->first()?->level)->toBe(GasAlarmLevel::Alarm)
        ->and(Alert::query()->where('alert_type', AlertType::GasAlarm)->count())->toBe(1);

    Event::assertDispatched(GasLiveUpdated::class);
});

it('does not evaluate or broadcast backfill exceedances', function () {
    Event::fake([GasLiveUpdated::class]);

    $plain = 'gas-bf';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(
            ['h2s_ppm' => 50],
            recordedAt: now()->subMinutes(30)->toIso8601String(),
            deviceRef: $device->reference,
        )],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasReading::query()->first()?->is_backfill)->toBeTrue()
        ->and(GasAlarm::query()->count())->toBe(0)
        ->and(Alert::query()->whereIn('alert_type', [AlertType::GasAlarm, AlertType::GasWarning])->count())->toBe(0);

    Event::assertNotDispatched(GasLiveUpdated::class);
});

it('escalates warning to alarm and closes the warning', function () {
    $plain = 'gas-esc';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 6], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->where('level', GasAlarmLevel::Warning)->whereNull('resolved_at')->count())->toBe(1);

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 12], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->where('level', GasAlarmLevel::Warning)->whereNull('resolved_at')->count())->toBe(0)
        ->and(GasAlarm::query()->where('level', GasAlarmLevel::Alarm)->whereNull('resolved_at')->count())->toBe(1);
});

it('requires two consecutive clean readings for hysteresis resolve', function () {
    Cache::flush();
    $plain = 'gas-hyst';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 12], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->whereNull('resolved_at')->count())->toBe(1);

    // Clear line for H2S: warn 5, alarm 10, margin 5% of 5 = 0.25 → clear below 4.75
    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 2], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->whereNull('resolved_at')->count())->toBe(1);

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 2], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->whereNull('resolved_at')->count())->toBe(0)
        ->and(Alert::query()->where('alert_type', AlertType::GasAlarm)->first()?->status)->toBe(AlertStatus::Resolved);
});

it('evaluates o2 low and high thresholds', function () {
    $plain = 'gas-o2';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['o2_pct' => 18.5], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->where('gas_type', GasType::O2Low)->count())->toBe(1);

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['o2_pct' => 24.0], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    expect(GasAlarm::query()->where('gas_type', GasType::O2High)->count())->toBe(1);
});

it('acknowledges alarm without resolving', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'gas-ack';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(['h2s_ppm' => 12], deviceRef: $device->reference)],
    ], gasIngestHeaders($plain))->assertAccepted();

    $alarm = GasAlarm::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('gas.alarms.acknowledge', $alarm))
        ->assertRedirect();

    expect($alarm->fresh()->acknowledged_by)->toBe($admin->id)
        ->and($alarm->fresh()->resolved_at)->toBeNull()
        ->and(Alert::query()->find($alarm->alert_id)?->status)->toBe(AlertStatus::Acknowledged);
});

it('updates thresholds with audit and manage permission', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->put(route('gas.thresholds.update'), [
            'thresholds' => [
                ['gas_type' => 'h2s', 'warning_level' => 4, 'alarm_level' => 8],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->put(route('gas.thresholds.update'), [
            'thresholds' => [
                ['gas_type' => 'h2s', 'warning_level' => 4, 'alarm_level' => 8],
            ],
        ])
        ->assertRedirect();

    expect((float) GasThreshold::query()->where('gas_type', GasType::H2s)->first()?->warning_level)->toBe(4.0);
});

it('returns live panels with stale badge', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->gasDetector()->create();
    GasReading::factory()->create([
        'device_id' => $device->id,
        'asset_id' => $device->asset_id,
        'recorded_at' => now()->subMinutes(20),
        'received_at' => now()->subMinutes(20),
        'h2s_ppm' => 1,
    ]);

    $this->actingAs($admin)
        ->getJson(route('gas.api.live'))
        ->assertOk()
        ->assertJsonPath('data.panels.0.is_stale', true)
        ->assertJsonPath('data.panels.0.device_id', $device->id);
});

it('gates gas views by permission', function () {
    $pm = User::factory()->withRole('Project Manager')->create();
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($pm)->get(route('gas.index'))->assertForbidden();
    $this->actingAs($operator)->get(route('gas.index'))->assertOk();
});

it('uses rollups for trends beyond 24 hours', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->gasDetector()->create();
    $svc = app(GasMonitoringService::class);

    // Seed via direct reading + rollup path
    $plain = 'gas-trend';
    $device->forceFill(['api_token_hash' => hash('sha256', $plain), 'token_issued_at' => now()])->save();

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [gasEvent(
            ['h2s_ppm' => 3],
            recordedAt: now()->subDays(3)->toIso8601String(),
            deviceRef: $device->reference,
        )],
    ], gasIngestHeaders($plain))->assertAccepted();

    $this->actingAs($admin)
        ->getJson(route('gas.trends.index', [
            'range' => 'custom',
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->toDateString(),
            'gas_type' => 'h2s',
            'device_id' => $device->id,
            'json' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('data.source', 'rollup');
});
