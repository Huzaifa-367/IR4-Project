<?php

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\GasAlarmLevel;
use App\Models\Alert;
use App\Models\Device;
use App\Models\GasAlarm;
use App\Models\GasReading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IngestTestClient;

afterEach(function (): void {
    Carbon::setTestNow();
    Cache::flush();
});

// DOC-21 scenario 6: Gas excursion ack/hysteresis + backfill no alarm.
it('scenario 06: live gas alarm acknowledge hysteresis resolve and backfill silent', function () {
    Carbon::setTestNow('2026-07-18 08:00:00');

    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'scenario-06';
    $device = Device::factory()->gasDetector()->withPlainToken($plain)->create();

    scenarioGasIngest($device, $plain, ['h2s_ppm' => 12]);
    $alarm = GasAlarm::query()->firstOrFail();
    expect($alarm->level)->toBe(GasAlarmLevel::Alarm)
        ->and(Alert::query()->where('alert_type', AlertType::GasAlarm)->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('gas.alarms.acknowledge', $alarm))
        ->assertRedirect();

    expect($alarm->fresh()->acknowledged_by)->toBe($admin->id)
        ->and($alarm->fresh()->resolved_at)->toBeNull()
        ->and(Alert::query()->find($alarm->alert_id)?->status)->toBe(AlertStatus::Acknowledged);

    scenarioGasIngest($device, $plain, ['h2s_ppm' => 2]);
    expect(GasAlarm::query()->whereNull('resolved_at')->count())->toBe(1);

    scenarioGasIngest($device, $plain, ['h2s_ppm' => 2]);
    expect(GasAlarm::query()->whereNull('resolved_at')->count())->toBe(0)
        ->and(Alert::query()->where('alert_type', AlertType::GasAlarm)->first()?->status)->toBe(AlertStatus::Resolved);

    $backfillPlain = 'scenario-06-bf';
    $backfillDevice = Device::factory()->gasDetector()->withPlainToken($backfillPlain)->create();
    scenarioGasIngest(
        $backfillDevice,
        $backfillPlain,
        ['h2s_ppm' => 50],
        now()->subMinutes(30)->toIso8601String(),
    );

    expect(GasReading::query()->where('device_id', $backfillDevice->id)->first()?->is_backfill)->toBeTrue()
        ->and(GasAlarm::query()->where('device_id', $backfillDevice->id)->count())->toBe(0)
        ->and(Alert::query()->whereIn('alert_type', [AlertType::GasAlarm, AlertType::GasWarning])->count())->toBe(1);
});
