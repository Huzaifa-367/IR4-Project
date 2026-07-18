<?php

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\ReviewStatus;
use App\Events\PpeViolationDetected;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Device;
use App\Models\IngestEvent;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function ppeIngestHeaders(string $plain): array
{
    return ['X-Device-Token' => $plain];
}

function ppeEvent(string $cameraRef, string $type = 'missing_helmet', ?string $uid = null, ?string $detectedAt = null): array
{
    return [
        'event_uid' => $uid ?? (string) Str::uuid(),
        'camera_ref' => $cameraRef,
        'event_type' => $type,
        'detected_at' => $detectedAt ?? now()->toIso8601String(),
        'confidence' => 0.91,
        'worker_count' => 1,
        'snapshot' => base64_encode('fake-jpeg'),
    ];
}

it('has no worker_id column on ppe_violations', function () {
    expect(Schema::hasColumn('ppe_violations', 'worker_id'))->toBeFalse()
        ->and(Schema::hasTable('ppe_violations'))->toBeTrue();
});

it('ingests a ppe violation into a row alert and broadcast', function () {
    Event::fake([PpeViolationDetected::class]);

    $plain = 'ppe-token';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-ppe-1']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent($camera->reference)],
    ], ppeIngestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $violation = PpeViolation::query()->first();
    expect($violation)->not->toBeNull()
        ->and($violation->camera_id)->toBe($camera->id)
        ->and($violation->review_status)->toBe(ReviewStatus::Unreviewed)
        ->and($violation->alert_id)->not->toBeNull()
        ->and(Schema::hasColumn('ppe_violations', 'worker_id'))->toBeFalse()
        ->and(IngestEvent::query()->where('stream', 'ppe_violations')->count())->toBe(0)
        ->and($camera->fresh()->last_frame_at)->not->toBeNull();

    expect(Alert::query()->where('alert_type', AlertType::PpeViolation)->count())->toBe(1);
    Event::assertDispatched(PpeViolationDetected::class);
});

it('routes fall events to fall_detection without a ppe row', function () {
    $plain = 'ppe-fall';
    Device::factory()->withPlainToken($plain)->create();
    $zone = Zone::factory()->create(['name' => 'Deck A']);
    $camera = Camera::factory()->create([
        'reference' => 'cam-fall',
        'meta' => ['zone_id' => $zone->id],
    ]);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent($camera->reference, 'fall')],
    ], ppeIngestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    expect(PpeViolation::query()->count())->toBe(0)
        ->and(Alert::query()->where('alert_type', AlertType::FallDetection)->count())->toBe(1);

    $alert = Alert::query()->where('alert_type', AlertType::FallDetection)->first();
    expect($alert?->payload['zone_id'] ?? null)->toBe($zone->id)
        ->and($alert?->payload['camera_ref'] ?? null)->toBe('cam-fall');
});

it('rejects unknown camera references', function () {
    $plain = 'ppe-unknown';
    Device::factory()->withPlainToken($plain)->create();

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent('missing-cam')],
    ], ppeIngestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('rejected.0.code', 'UNKNOWN_REFERENCE');
});

it('is idempotent on camera_id and event_uid', function () {
    $plain = 'ppe-idem';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-idem']);
    $uid = (string) Str::uuid();

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent($camera->reference, 'missing_vest', $uid)],
    ], ppeIngestHeaders($plain))->assertAccepted()->assertJsonPath('accepted', 1);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent($camera->reference, 'missing_vest', $uid)],
    ], ppeIngestHeaders($plain))->assertAccepted()->assertJsonPath('duplicates', 1);

    expect(PpeViolation::query()->count())->toBe(1);
});

it('stores backfill without broadcasting', function () {
    Event::fake([PpeViolationDetected::class]);

    $plain = 'ppe-backfill';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-bf']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent(
            $camera->reference,
            'missing_helmet',
            null,
            now()->subMinutes(30)->toIso8601String(),
        )],
    ], ppeIngestHeaders($plain))->assertAccepted()->assertJsonPath('accepted', 1);

    $violation = PpeViolation::query()->first();
    expect($violation?->is_backfill)->toBeTrue()
        ->and($violation?->alert_id)->toBeNull()
        ->and(Alert::query()->where('alert_type', AlertType::PpeViolation)->count())->toBe(0);

    Event::assertNotDispatched(PpeViolationDetected::class);
});

it('reviews confirm and false positive resolving the alert', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'ppe-review';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-rev']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent($camera->reference)],
    ], ppeIngestHeaders($plain))->assertAccepted();

    $violation = PpeViolation::query()->firstOrFail();
    $alertId = $violation->alert_id;

    $this->actingAs($admin)
        ->post(route('ppe.violations.review', $violation), [
            'status' => 'false_positive',
            'note' => 'Dust glare false positive case',
        ])
        ->assertRedirect();

    expect($violation->fresh()->review_status)->toBe(ReviewStatus::FalsePositive)
        ->and(Alert::query()->find($alertId)?->status)->toBe(AlertStatus::Resolved);
});

it('bulk reviews multiple violations', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $a = PpeViolation::factory()->create();
    $b = PpeViolation::factory()->create();

    $this->actingAs($admin)
        ->post(route('ppe.violations.bulk-review'), [
            'ids' => [$a->id, $b->id],
            'status' => 'confirmed',
            'note' => 'Bulk confirm during triage pass',
        ])
        ->assertRedirect();

    expect($a->fresh()->review_status)->toBe(ReviewStatus::Confirmed)
        ->and($b->fresh()->review_status)->toBe(ReviewStatus::Confirmed);
});

it('excludes false positives from summary', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    PpeViolation::factory()->create(['detected_at' => now()]);
    PpeViolation::factory()->falsePositive()->create(['detected_at' => now()]);

    $this->actingAs($admin)
        ->getJson(route('ppe.api.summary', ['range' => 'daily']))
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.excluded_false_positives', 1);
});

it('exposes signed snapshot urls never raw paths', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $violation = PpeViolation::factory()->create([
        'snapshot_path' => 'snapshots/2026/07/18/test.jpg',
    ]);

    $this->actingAs($admin)
        ->get(route('ppe.violations.show', $violation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ppe/violations/show')
            ->has('violation.snapshot_url')
            ->missing('violation.snapshot_path')
            ->missing('violation.worker_id'));
});

it('gates view review export and live permissions', function () {
    $viewer = User::factory()->withRole('Project Manager')->create();
    $violation = PpeViolation::factory()->create();

    $this->actingAs($viewer)->get(route('ppe.violations.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('live.index'))->assertForbidden();

    $operator = User::factory()->withRole('SCC Operator')->create();
    $this->actingAs($operator)->get(route('ppe.violations.index'))->assertOk();
    $this->actingAs($operator)->get(route('live.index'))->assertOk();

    $this->actingAs($viewer)
        ->post(route('ppe.violations.review', $violation), [
            'status' => 'confirmed',
            'note' => 'Should not be allowed here',
        ])
        ->assertForbidden();
});

it('serves browser playback urls without exposing rtsp credentials', function () {
    config()->set(
        'camera_stream.browser_url_template',
        'http://127.0.0.1:8888/{reference}',
    );
    $operator = User::factory()->withRole('SCC Operator')->create();
    Camera::factory()->create([
        'reference' => 'cam-test-01',
        'stream_url' => 'rtsp://operator:secret@10.0.0.5/stream1',
    ]);

    $this->actingAs($operator)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cameras.0.playback_url', 'http://127.0.0.1:8888/cam-test-01')
            ->missing('cameras.0.stream_url'));
});

it('exports csv excluding false positives', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    PpeViolation::factory()->create(['detected_at' => now()]);
    PpeViolation::factory()->falsePositive()->create(['detected_at' => now()]);

    $this->actingAs($admin)
        ->post(route('ppe.violations.export'), [
            'format' => 'csv',
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertHeader('content-disposition');
});
