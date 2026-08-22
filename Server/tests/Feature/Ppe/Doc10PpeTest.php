<?php

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\HardwareStatus;
use App\Enums\ReviewStatus;
use App\Enums\ViolationType;
use App\Events\PpeViolationDetected;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Device;
use App\Models\IngestEvent;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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

it('accepts helmet and vest ingest without a camera still', function () {
    $plain = 'ppe-no-still';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-no-still']);
    $event = ppeEvent($camera->reference, 'missing_vest');
    unset($event['snapshot']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [$event],
    ], ppeIngestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $violation = PpeViolation::query()->first();
    expect($violation?->snapshot_path)->toBeNull()
        ->and($violation?->violation_type->value)->toBe('missing_vest');
});

it('stores fall events as ppe rows and raises fall_detection', function () {
    Event::fake([PpeViolationDetected::class]);

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

    $violation = PpeViolation::query()->first();
    expect($violation)->not->toBeNull()
        ->and($violation?->violation_type)->toBe(ViolationType::Fall)
        ->and($violation?->alert_id)->not->toBeNull()
        ->and(Alert::query()->where('alert_type', AlertType::FallDetection)->count())->toBe(1);

    $alert = Alert::query()->where('alert_type', AlertType::FallDetection)->first();
    expect($alert?->payload['zone_id'] ?? null)->toBe($zone->id)
        ->and($alert?->payload['camera_id'] ?? null)->toBe($camera->id)
        ->and($alert?->payload['camera_ref'] ?? null)->toBe('cam-fall')
        ->and($alert?->payload['ppe_violation_id'] ?? null)->toBe($violation?->id);

    Event::assertDispatched(PpeViolationDetected::class);
});

it('stores working-at-heights as missing_harness and raises height_without_harness', function () {
    $plain = 'ppe-heights';
    Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-heights']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [ppeEvent($camera->reference, 'missing_harness')],
    ], ppeIngestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $violation = PpeViolation::query()->first();
    expect($violation?->violation_type)->toBe(ViolationType::MissingHarness)
        ->and($violation?->violation_type->label())->toBe('Working at heights')
        ->and(Alert::query()->where('alert_type', AlertType::HeightWithoutHarness)->count())->toBe(1);
});

it('lists every ppe type including fall and working at heights', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get(route('ppe.violations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ppe/violations/index')
            ->where('violationTypes', fn ($types) => collect($types)->pluck('value')->all() === [
                'missing_helmet',
                'missing_vest',
                'missing_harness',
                'missing_mask',
                'fall',
            ])
            ->where('violationTypes.2.label', 'Working at heights')
            ->where('violationTypes.4.label', 'Fall detection'));
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
            ->where('cameras.0.playback_url', 'http://127.0.0.1:8888/cam-test-01/')
            ->missing('cameras.0.stream_url'));
});

it('uses same-origin hls proxy template on the live wall', function () {
    config()->set('camera_stream.browser_url_template', '/hls/{reference}/');
    $operator = User::factory()->withRole('SCC Operator')->create();
    Camera::factory()->create(['reference' => 'cam-proxy-01']);

    $this->actingAs($operator)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cameras.0.playback_url', '/hls/cam-proxy-01/'));
});

it('includes cameras on the live wall poll snapshot', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $camera = Camera::factory()->create([
        'name' => 'Wall cam',
        'status' => HardwareStatus::Online,
        'last_frame_at' => now(),
    ]);

    $this->actingAs($operator)
        ->getJson(route('live.violations'))
        ->assertOk()
        ->assertJsonPath('data.cameras.0.id', $camera->id)
        ->assertJsonPath('data.cameras.0.name', 'Wall cam')
        ->assertJsonPath('data.cameras.0.is_online', true)
        ->assertJsonStructure(['data' => ['cameras', 'violations']]);
});

it('renders the live wall kiosk without the dashboard display route', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get(route('live.index', ['display' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('display/live')
            ->where('displayMode', true));

    $this->get('/display')->assertNotFound();
});

it('rejects mutating methods on the hls proxy', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->post('/hls/cam-ppe-01/')
        ->assertMethodNotAllowed();
});

it('proxies mediamtx hls through same-origin /hls', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://192.168.3.149:9997');
    config()->set('camera_stream.mediamtx.hls_url', 'http://mediamtx.test:8888');
    Http::fake([
        'mediamtx.test:8888/*' => Http::response('<html>player</html>', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get('/hls/cam-ppe-01/')
        ->assertOk()
        ->assertStreamedContent('<html>player</html>');

    Http::assertSent(fn ($request) => $request->url() === 'http://mediamtx.test:8888/cam-ppe-01/');
});

it('follows mediamtx hls cookie redirect server-side for playlists', function () {
    config()->set('camera_stream.mediamtx.hls_url', 'http://mediamtx.test:8888');
    Http::fake([
        'mediamtx.test:8888/CAM-FIXED-01/index.m3u8' => Http::response('', 302, [
            'Location' => '/CAM-FIXED-01/index.m3u8?cookieCheck=1',
            'Set-Cookie' => 'cookieCheck=1',
        ]),
        'mediamtx.test:8888/CAM-FIXED-01/index.m3u8?cookieCheck=1' => Http::response(
            "#EXTM3U\n#EXT-X-VERSION:10\nvideo1_stream.m3u8\n",
            200,
            ['Content-Type' => 'application/vnd.apple.mpegurl'],
        ),
    ]);

    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get('/hls/CAM-FIXED-01/index.m3u8')
        ->assertOk()
        ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
        ->assertSee('#EXTM3U');

    Http::assertSentCount(2);
});

it('reuses mediamtx hls cookies for child playlists and segments', function () {
    config()->set('camera_stream.mediamtx.hls_url', 'http://mediamtx.test:8888');
    Http::fake([
        'mediamtx.test:8888/CAM-FIXED-01/index.m3u8' => Http::sequence()
            ->push('', 302, [
                'Location' => '/CAM-FIXED-01/index.m3u8?cookieCheck=1',
                'Set-Cookie' => 'cookieCheck=1',
            ])
            ->push(
                "#EXTM3U\n#EXT-X-VERSION:10\nvideo1_stream.m3u8\n",
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl'],
            )
            ->push(
                "#EXTM3U\n#EXT-X-VERSION:10\nvideo1_stream.m3u8\n",
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl'],
            ),
        'mediamtx.test:8888/CAM-FIXED-01/index.m3u8?cookieCheck=1' => Http::response(
            "#EXTM3U\n#EXT-X-VERSION:10\nvideo1_stream.m3u8\n",
            200,
            ['Content-Type' => 'application/vnd.apple.mpegurl'],
        ),
        'mediamtx.test:8888/CAM-FIXED-01/video1_stream.m3u8' => Http::response(
            "#EXTM3U\n#EXT-X-VERSION:10\n#EXT-X-MAP:URI=\"init.mp4\"\n",
            200,
            ['Content-Type' => 'application/vnd.apple.mpegurl'],
        ),
        'mediamtx.test:8888/CAM-FIXED-01/init.mp4' => Http::response('init-bytes', 200, [
            'Content-Type' => 'video/mp4',
        ]),
    ]);

    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get('/hls/CAM-FIXED-01/video1_stream.m3u8')
        ->assertOk()
        ->assertSee('#EXT-X-MAP');

    $this->actingAs($operator)
        ->get('/hls/CAM-FIXED-01/init.mp4')
        ->assertOk()
        ->assertStreamedContent('init-bytes');
});

it('does not refresh idle timeout on hls media segments', function () {
    config()->set('camera_stream.mediamtx.hls_url', 'http://mediamtx.test:8888');
    Http::fake([
        'mediamtx.test:8888/*' => Http::response('segment', 200, [
            'Content-Type' => 'video/mp4',
        ]),
    ]);

    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->withSession(['last_activity_at' => now()->subMinutes(5)->getTimestamp()])
        ->get('/hls/CAM-FIXED-03/seg321.mp4')
        ->assertOk();

    expect(session('last_activity_at'))->toBe(now()->subMinutes(5)->getTimestamp());
});

it('does not repeat similar queries on hls media segments', function () {
    config()->set('camera_stream.mediamtx.hls_url', 'http://mediamtx.test:8888');
    Http::fake([
        'mediamtx.test:8888/*' => Http::response('segment', 200, [
            'Content-Type' => 'video/mp4',
        ]),
    ]);

    $operator = User::factory()->withRole('SCC Operator')->create();
    $this->actingAs($operator);

    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->get('/hls/CAM-FIXED-03/seg321.mp4')->assertOk();

    expect(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'settings')))->toBeEmpty();

    $maxRepeats = collect($queries)->countBy(fn (string $sql) => $sql)->max() ?? 0;
    expect($maxRepeats)->toBeLessThan(3);
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
