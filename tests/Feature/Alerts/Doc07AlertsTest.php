<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\HardwareStatus;
use App\Events\AlertRaised;
use App\Events\AlertUpdated;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use App\Services\AlertService;
use App\Services\AssetHealthService;
use App\Services\HardwareRegistryService;
use Illuminate\Support\Facades\Event;

it('raises an alert with AlertPolicy defaults and broadcasts AlertRaised', function () {
    Event::fake([AlertRaised::class, AlertUpdated::class]);

    $alert = app(AlertService::class)->raise(
        type: AlertType::FallDetection,
        title: 'Fall near Pole 3',
        payload: ['worker_id' => 42, 'worker_name' => 'Ada'],
    );

    expect($alert->severity)->toBe(AlertSeverity::Critical)
        ->and($alert->audible)->toBeTrue()
        ->and($alert->payload['suggested_action'])->toBe('create_incident')
        ->and($alert->status)->toBe(AlertStatus::Open)
        ->and(Alert::query()->count())->toBe(1);

    Event::assertDispatched(AlertRaised::class);
});

it('dedupes open alerts by key and bumps occurrences without a second row', function () {
    Event::fake([AlertRaised::class, AlertUpdated::class]);
    $service = app(AlertService::class);

    $first = $service->raise(
        type: AlertType::DeviceOffline,
        title: 'Device offline: Reader A',
        dedupeKey: 'device_offline:9',
    );
    $second = $service->raise(
        type: AlertType::DeviceOffline,
        title: 'Device offline: Reader A',
        payload: ['last_seen' => 'stale'],
        dedupeKey: 'device_offline:9',
    );

    expect(Alert::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->fresh()->occurrences)->toBe(2)
        ->and($second->fresh()->payload['last_seen'])->toBe('stale');

    Event::assertDispatched(AlertRaised::class, 1);
    Event::assertDispatched(AlertUpdated::class, 1);
});

it('does not auto-create incident or lsr rows when raising suggested_action alerts', function () {
    app(AlertService::class)->raise(type: AlertType::FallDetection, title: 'Fall');
    app(AlertService::class)->raise(type: AlertType::PpeViolation, title: 'No helmet');

    expect(Alert::query()->count())->toBe(2)
        ->and(\App\Models\HseIncident::query()->count())->toBe(0)
        ->and(\App\Models\LsrViolation::query()->count())->toBe(0);
});

it('acknowledges and resolves alerts and forbids resolve without resolve-alerts', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $pm = User::factory()->withRole('Project Manager')->create();
    $alert = Alert::factory()->create();

    $this->actingAs($operator)
        ->post(route('alerts.acknowledge', $alert))
        ->assertRedirect();

    expect($alert->fresh()->status)->toBe(AlertStatus::Acknowledged)
        ->and(AuditLog::query()->where('event_type', 'acknowledged')->exists())->toBeTrue();

    $this->actingAs($pm)
        ->post(route('alerts.resolve', $alert))
        ->assertForbidden();

    $this->actingAs($operator)
        ->post(route('alerts.resolve', $alert), ['note' => 'Cleared after check'])
        ->assertRedirect();

    expect($alert->fresh()->status)->toBe(AlertStatus::Resolved)
        ->and($alert->fresh()->payload['resolve_note'] ?? null)->toBe('Cleared after check');
});

it('returns open alerts for the poll endpoint and lists the alert centre', function () {
    $user = User::factory()->withRole('SCC Operator')->create();
    Alert::factory()->create(['title' => 'Open A']);
    Alert::factory()->resolved()->create(['title' => 'Done']);

    $this->actingAs($user)
        ->getJson(route('alerts.open'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Open A');

    $this->actingAs($user)
        ->get(route('alerts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('alerts/index')
            ->has('alerts.data', 1));
});

it('has no user-facing create alert endpoint', function () {
    $admin = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($admin)
        ->post('/alerts', ['title' => 'Nope'])
        ->assertStatus(405);
});

it('markStale raises a deduped device_offline alert and heartbeat resolves it', function () {
    $device = Device::factory()->create([
        'status' => HardwareStatus::Online,
        'last_seen_at' => now()->subMinutes(20),
    ]);

    app(AssetHealthService::class)->markStale();
    app(AssetHealthService::class)->markStale();

    $alert = Alert::query()->where('dedupe_key', "device_offline:{$device->id}")->first();

    expect($device->fresh()->status)->toBe(HardwareStatus::Offline)
        ->and($alert)->not->toBeNull()
        ->and($alert->occurrences)->toBe(2)
        ->and(Alert::query()->where('dedupe_key', "device_offline:{$device->id}")->count())->toBe(1);

    $result = app(HardwareRegistryService::class)->issueToken($device->fresh());
    $this->postJson(route('api.devices.heartbeat', $device), [], [
        'X-Device-Token' => $result['plain_token'],
    ])->assertOk();

    expect($alert->fresh()->status)->toBe(AlertStatus::Resolved);
});

it('mutes audible when alert.audible_enabled is false', function () {
    app(\App\Services\SettingsService::class)->set('alert.audible_enabled', false);

    $alert = app(AlertService::class)->raise(type: AlertType::GasAlarm, title: 'H2S alarm');

    expect($alert->audible)->toBeFalse();
});

it('strips worker identity from alert payload without view-worker-identity', function () {
    $user = User::factory()->withRole('Project Manager')->create();
    Alert::factory()->create([
        'payload' => [
            'worker_id' => 7,
            'worker_name' => 'Secret Name',
            'badge_number' => 'B-1',
        ],
    ]);

    $this->actingAs($user)
        ->getJson(route('alerts.open'))
        ->assertOk()
        ->assertJsonPath('data.0.payload.worker_name', 'Worker #7')
        ->assertJsonMissingPath('data.0.payload.badge_number');
});
