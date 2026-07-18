<?php

use App\Enums\AlertType;
use App\Enums\IncidentSource;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Enums\ReviewStatus;
use App\Enums\ZoneType;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Device;
use App\Models\HseIncident;
use App\Models\IncidentEvidence;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Services\LsrService;
use App\Services\ReaderBindingService;
use App\Services\TagService;
use App\Services\TrackingService;
use Illuminate\Support\Carbon;
use Tests\Support\IngestTestClient;

afterEach(function (): void {
    Carbon::setTestNow();
});

// DOC-21 scenario 3: Zone rule → alert → user-submitted LSR (no auto-create).
it('scenario 03: red zone intrusion alert prefill then user lsr with mandatory close', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $plain = 'scenario-03';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $red = Zone::factory()->create(['zone_type' => ZoneType::RestrictedRed]);
    app(ReaderBindingService::class)->bind($reader, $red, now()->subDay(), $operator);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $operator);
    WorkerPosition::query()->where('tag_id', $tag->id)->update(['is_on_site' => true]);

    scenarioIngestTag($reader, $plain, $tag->tag_uid, now());

    $alert = Alert::query()->where('alert_type', AlertType::RedZoneIntrusion)->firstOrFail();
    expect(LsrViolation::query()->count())->toBe(0);

    $prefill = app(LsrService::class)->prefillFromAlert($alert);
    expect($prefill['category'])->toBe(LsrCategory::RedZoneIntrusion->value);

    $this->actingAs($operator)
        ->post(route('hse.lsr.store'), [
            'category' => $prefill['category'],
            'occurred_at' => now()->toDateTimeString(),
            'zone_id' => $red->id,
            'worker_id' => $worker->id,
            'alert_id' => $alert->id,
            'description' => $prefill['description'],
        ])
        ->assertRedirect();

    $lsr = LsrViolation::query()->firstOrFail();
    expect($lsr->alert_id)->toBe($alert->id)
        ->and($lsr->status)->toBe(LsrStatus::Open);

    $this->post(route('hse.lsr.close', $lsr), [])
        ->assertSessionHasErrors('action_taken');

    $this->post(route('hse.lsr.close', $lsr), [
        'action_taken' => 'Worker escorted out and toolbox talk completed.',
    ])->assertRedirect();

    expect($lsr->fresh()->status)->toBe(LsrStatus::Closed);
});

// DOC-21 scenario 4: PPE → alert → linkable anonymous LSR.
it('scenario 04: ppe ingest raises alert then user logs anonymous lsr link', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $plain = 'scenario-04';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-scenario-04']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [scenarioPpeEvent($camera->reference)],
    ], IngestTestClient::headers($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $violation = PpeViolation::query()->firstOrFail();
    expect($violation->alert_id)->not->toBeNull()
        ->and(Alert::query()->where('alert_type', AlertType::PpeViolation)->count())->toBe(1);

    $this->actingAs($operator)
        ->post(route('hse.lsr.store'), [
            'category' => LsrCategory::MissingPpe->value,
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'ppe_violation_id' => $violation->id,
            'description' => 'Harness missing at height work front.',
        ])
        ->assertRedirect();

    $lsr = LsrViolation::query()->firstOrFail();
    expect($lsr->worker_id)->toBeNull()
        ->and($lsr->ppe_violation_id)->toBe($violation->id)
        ->and($lsr->status)->toBe(LsrStatus::Open);
});

// DOC-21 scenario 5: Fall + stationary → worker_down → user incident.
it('scenario 05: fall and stationary correlate to worker down then user incident', function () {
    Carbon::setTestNow('2026-07-18 11:00:00');

    $manager = User::factory()->withRole('Safety Manager')->create();
    $zone = Zone::factory()->create(['name' => 'Deck 3', 'zone_type' => ZoneType::Work]);
    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $manager);

    WorkerPosition::query()->where('tag_id', $tag->id)->update([
        'zone_id' => $zone->id,
        'is_on_site' => true,
        'last_seen_at' => now()->subMinutes(16),
    ]);

    app(TrackingService::class)->checkStationaryTags(now());

    expect(Alert::query()->where('alert_type', AlertType::StationaryTag)->exists())->toBeTrue();

    $plain = 'scenario-05';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create([
        'reference' => 'cam-scenario-05',
        'meta' => ['zone_id' => $zone->id],
    ]);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [scenarioPpeEvent($camera->reference, 'fall', now()->subMinute()->toIso8601String())],
    ], IngestTestClient::headers($plain))->assertAccepted();

    $workerDown = Alert::query()->where('alert_type', AlertType::WorkerDown)->first();
    expect($workerDown)->not->toBeNull()
        ->and(HseIncident::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->post(route('hse.incidents.store'), [
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'alert_id' => $workerDown->id,
            'zone_id' => $zone->id,
        ])
        ->assertRedirect();

    $incident = HseIncident::query()->firstOrFail();
    expect($incident->source)->toBe(IncidentSource::FromAlert)
        ->and($incident->alert_id)->toBe($workerDown->id)
        ->and(IncidentEvidence::query()->where('evidence_type', 'rfid_zone_snapshot')->exists())->toBeTrue();
});
