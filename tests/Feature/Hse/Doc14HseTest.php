<?php

use App\Enums\AlertType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Involvement;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Models\Alert;
use App\Models\HseIncident;
use App\Models\IncidentEvidence;
use App\Models\IncidentPersonnel;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Services\AlertService;
use App\Services\IncidentService;
use App\Services\LsrService;

it('does not auto-create incidents or lsr when alerts are raised', function () {
    app(AlertService::class)->raise(type: AlertType::FallDetection, title: 'Fall');
    app(AlertService::class)->raise(type: AlertType::PpeViolation, title: 'No helmet');

    expect(HseIncident::query()->count())->toBe(0)
        ->and(LsrViolation::query()->count())->toBe(0);
});

it('creates a manual incident and classifies with mandatory action fields', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $zone = Zone::factory()->create();

    $this->actingAs($manager)
        ->post(route('hse.incidents.store'), [
            'occurred_at' => now()->subMinutes(5)->toDateTimeString(),
            'zone_id' => $zone->id,
            'nature_of_incident' => 'Observed near miss near scaffold.',
        ])
        ->assertRedirect();

    $incident = HseIncident::query()->firstOrFail();
    expect($incident->source)->toBe(IncidentSource::Manual)
        ->and($incident->status)->toBe(IncidentStatus::Open)
        ->and($incident->incident_number)->toStartWith('INC-'.now()->format('Y').'-');

    $this->put(route('hse.incidents.classify', $incident), [
        'incident_type' => IncidentType::NearMiss->value,
        'severity' => IncidentSeverity::Medium->value,
        'nature_of_incident' => 'Worker slipped but regained footing.',
        'immediate_action' => 'Area dried and cordoned immediately.',
        'corrective_action' => 'Anti-slip mats installed and toolbox talk held.',
    ])->assertRedirect();

    expect($incident->fresh()?->status)->toBe(IncidentStatus::Classified);

    $this->put(route('hse.incidents.classify', $incident->fresh()), [
        'incident_type' => IncidentType::NearMiss->value,
        'severity' => IncidentSeverity::Medium->value,
        'nature_of_incident' => 'short',
        'immediate_action' => 'short',
        'corrective_action' => 'short',
    ])->assertSessionHasErrors();
});

it('creates an incident from an alert with frozen RFID roster evidence', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $zone = Zone::factory()->create();
    $otherZone = Zone::factory()->create();
    $worker = Worker::factory()->create();
    $tag = \App\Models\RfidTag::factory()->create(['worker_id' => $worker->id]);
    $reader = \App\Models\Device::factory()->create();
    $occurredAt = now()->subMinute();

    // In zone before occurred_at — should be included.
    \App\Models\TagReading::factory()->create([
        'tag_id' => $tag->id,
        'reader_device_id' => $reader->id,
        'zone_id' => $zone->id,
        'recorded_at' => $occurredAt->copy()->subMinutes(2),
        'received_at' => $occurredAt->copy()->subMinutes(2),
    ]);
    // Left the zone after occurred_at — must not remove them from the freeze.
    \App\Models\TagReading::factory()->create([
        'tag_id' => $tag->id,
        'reader_device_id' => $reader->id,
        'zone_id' => $otherZone->id,
        'recorded_at' => $occurredAt->copy()->addMinute(),
        'received_at' => $occurredAt->copy()->addMinute(),
    ]);
    // Current position alone must not drive the snapshot.
    WorkerPosition::factory()->create([
        'worker_id' => $worker->id,
        'zone_id' => $otherZone->id,
        'is_on_site' => true,
    ]);

    $alert = app(AlertService::class)->raise(
        type: AlertType::FallDetection,
        title: 'Fall near work front',
        payload: [
            'zone_id' => $zone->id,
            'detected_at' => $occurredAt->toIso8601String(),
        ],
    );

    $prefill = app(IncidentService::class)->prefillFromAlert($alert);
    expect($prefill['alert_id'])->toBe($alert->id)
        ->and(HseIncident::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->post(route('hse.incidents.store'), [
            'occurred_at' => $occurredAt->toDateTimeString(),
            'alert_id' => $alert->id,
            'zone_id' => $zone->id,
        ])
        ->assertRedirect();

    $incident = HseIncident::query()->firstOrFail();
    expect($incident->source)->toBe(IncidentSource::FromAlert)
        ->and($incident->alert_id)->toBe($alert->id)
        ->and(IncidentPersonnel::query()->where('involvement', Involvement::PresentInZone->value)->count())->toBe(1)
        ->and(IncidentEvidence::query()->where('evidence_type', 'rfid_zone_snapshot')->whereNull('added_by')->count())->toBe(1);

    $worker->update(['name' => 'Moved Elsewhere']);
    $snapshot = IncidentEvidence::query()->where('evidence_type', 'rfid_zone_snapshot')->firstOrFail();
    expect($snapshot->payload['workers'][0]['worker_id'])->toBe($worker->id)
        ->and($snapshot->payload['workers'][0]['tag_id'])->toBe($tag->id);
});

it('requires close_note for non-classified close and allows classified close', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $open = HseIncident::factory()->create(['status' => IncidentStatus::Open]);

    $this->actingAs($manager)
        ->post(route('hse.incidents.close', $open), [])
        ->assertSessionHasErrors('close_note');

    $this->post(route('hse.incidents.close', $open), [
        'close_note' => 'Verified false alarm after camera review.',
    ])->assertRedirect();

    expect($open->fresh()?->status)->toBe(IncidentStatus::Closed);

    $classified = HseIncident::factory()->classified()->create();
    $this->post(route('hse.incidents.close', $classified))->assertRedirect();
    expect($classified->fresh()?->status)->toBe(IncidentStatus::Closed);
});

it('closes lsr only with action_taken and keeps ppe-linked worker null', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $worker = Worker::factory()->create();
    $ppe = PpeViolation::factory()->create();

    $this->actingAs($operator)
        ->post(route('hse.lsr.store'), [
            'category' => LsrCategory::MissingPpe->value,
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'worker_id' => $worker->id,
            'ppe_violation_id' => $ppe->id,
            'description' => 'Repeated missing harness observation.',
        ])
        ->assertRedirect();

    $lsr = LsrViolation::query()->firstOrFail();
    expect($lsr->worker_id)->toBeNull()
        ->and($lsr->ppe_violation_id)->toBe($ppe->id)
        ->and($lsr->status)->toBe(LsrStatus::Open);

    $this->post(route('hse.lsr.close', $lsr), [])
        ->assertSessionHasErrors('action_taken');

    $this->post(route('hse.lsr.close', $lsr), [
        'action_taken' => 'Work stopped and harness issued before restart.',
    ])->assertRedirect();

    expect($lsr->fresh()?->status)->toBe(LsrStatus::Closed);
});

it('prefills lsr from alert without inserting until submit', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $zone = Zone::factory()->create();
    $worker = Worker::factory()->create();

    $alert = app(AlertService::class)->raise(
        type: AlertType::RedZoneIntrusion,
        title: 'Red zone entry',
        payload: [
            'zone_id' => $zone->id,
            'worker_id' => $worker->id,
        ],
    );

    $prefill = app(LsrService::class)->prefillFromAlert($alert);
    expect($prefill['category'])->toBe(LsrCategory::RedZoneIntrusion->value)
        ->and(LsrViolation::query()->count())->toBe(0);

    $this->actingAs($operator)
        ->post(route('hse.lsr.store'), [
            'category' => $prefill['category'],
            'occurred_at' => now()->toDateTimeString(),
            'zone_id' => $zone->id,
            'worker_id' => $worker->id,
            'alert_id' => $alert->id,
            'description' => $prefill['description'],
        ])
        ->assertRedirect();

    expect(LsrViolation::query()->count())->toBe(1)
        ->and(LsrViolation::query()->first()?->alert_id)->toBe($alert->id);
});

it('opens create via index dialog props and redirects legacy create urls', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $alert = app(AlertService::class)->raise(
        type: AlertType::FallDetection,
        title: 'Fall',
    );

    $this->actingAs($operator)
        ->get(route('hse.incidents.create', ['alert_id' => $alert->id]))
        ->assertRedirect(route('hse.incidents.index', ['alert_id' => $alert->id]));

    $this->actingAs($operator)
        ->get(route('hse.lsr.create', ['alert_id' => $alert->id]))
        ->assertRedirect(route('hse.lsr.index', ['alert_id' => $alert->id]));

    $this->actingAs($operator)
        ->get(route('hse.incidents.index', ['alert_id' => $alert->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('hse/incidents/index')
            ->where('prefill.alert_id', $alert->id)
            ->has('zones'));

    $this->actingAs($operator)
        ->get(route('hse.lsr.index', ['alert_id' => $alert->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('hse/lsr/index')
            ->where('prefill.alert_id', $alert->id)
            ->has('zones')
            ->has('workers'));
});

it('logs permit categories manually and returns summary counts', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->post(route('hse.lsr.store'), [
            'category' => LsrCategory::HotWorkWithoutFireWatch->value,
            'occurred_at' => now()->toDateTimeString(),
            'description' => 'Hot work observed without fire watch.',
        ])
        ->assertRedirect();

    $this->get(route('hse.lsr.api.summary'))
        ->assertOk()
        ->assertJsonPath('open', 1);

    $this->get(route('hse.lsr.summary'))->assertOk();
});

it('gates incident classify and lsr close by permission', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $incident = HseIncident::factory()->create();
    $lsr = LsrViolation::factory()->create();

    $this->actingAs($operator)
        ->put(route('hse.incidents.classify', $incident), [
            'incident_type' => IncidentType::Other->value,
            'severity' => IncidentSeverity::Low->value,
            'nature_of_incident' => 'Enough characters here.',
            'immediate_action' => 'Enough characters here.',
            'corrective_action' => 'Enough characters here.',
        ])
        ->assertForbidden();

    $viewer = User::factory()->withRole('Project Manager')->create();
    $this->actingAs($viewer)
        ->post(route('hse.lsr.close', $lsr), [
            'action_taken' => 'Enough characters for action taken.',
        ])
        ->assertForbidden();
});
