<?php

use App\Enums\DeviceType;
use App\Enums\Direction;
use App\Enums\HardwareStatus;
use App\Enums\HeadcountSource;
use App\Enums\ZoneType;
use App\Events\HeadcountUpdated;
use App\Models\Camera;
use App\Models\CameraHeadcountReading;
use App\Models\Device;
use App\Models\EntryExitLog;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Services\Hardware\HardwareRegistryService;
use App\Services\Settings\SettingsService;
use App\Services\Tracking\CameraZoneBindingService;
use App\Services\Tracking\ReaderBindingService;
use App\Services\Tracking\TagService;
use App\Services\Tracking\TrackingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Cache::flush();
    app(SettingsService::class)->set(
        'tracking.headcount_source',
        HeadcountSource::Camera->value,
        confirmed: true,
    );
});

function edgeWithToken(string $reference = 'edge-hc-01'): array
{
    $edge = Device::factory()->withoutToken()->create([
        'reference' => $reference,
        'device_type' => DeviceType::EdgeCompute,
        'name' => 'Edge Headcount',
    ]);
    $plain = app(HardwareRegistryService::class)->issueToken($edge)['plain_token'];

    return [$edge, $plain];
}

function boundHeadcountCamera(User $admin, string $reference, Zone $zone): Camera
{
    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'reference' => $reference,
    ]);
    app(CameraZoneBindingService::class)->bind($camera, $zone, now()->subDay(), $admin);

    return $camera;
}

it('ingests camera headcount with zone snapshot and builds by_zone totals', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    [, $plain] = edgeWithToken();
    $zoneA = Zone::factory()->create(['name' => 'Bay A', 'zone_type' => ZoneType::Work]);
    $zoneB = Zone::factory()->create(['name' => 'Bay B', 'zone_type' => ZoneType::Work]);
    $camA = boundHeadcountCamera($admin, 'CAM-HC-A', $zoneA);
    $camB = boundHeadcountCamera($admin, 'CAM-HC-B', $zoneB);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => now()->toIso8601String(),
                'count' => 7,
                'camera_ref' => $camA->reference,
            ],
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => now()->toIso8601String(),
                'count' => 5,
                'camera_ref' => $camB->reference,
            ],
        ],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', 2);

    expect(CameraHeadcountReading::query()->where('camera_id', $camA->id)->value('zone_id'))->toBe($zoneA->id);

    $snapshot = app(TrackingService::class)->headcountSnapshot();
    expect($snapshot['total_on_site'])->toBe(12)
        ->and($snapshot['source'])->toBe('camera')
        ->and(collect($snapshot['by_zone'])->pluck('count', 'zone_id')->all())
        ->toMatchArray([
            $zoneA->id => 7,
            $zoneB->id => 5,
        ]);
});

it('dedupes headcount event_uid and rejects unknown camera_ref', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    [, $plain] = edgeWithToken('edge-hc-02');
    $zone = Zone::factory()->create(['zone_type' => ZoneType::Work]);
    $camera = boundHeadcountCamera($admin, 'CAM-HC-DUP', $zone);
    $uid = (string) Str::uuid();

    $payload = [
        'events' => [[
            'event_uid' => $uid,
            'recorded_at' => now()->toIso8601String(),
            'count' => 3,
            'camera_ref' => $camera->reference,
        ]],
    ];

    $this->postJson(route('api.ingest.headcount-readings'), $payload, [
        'X-Device-Token' => $plain,
    ])->assertAccepted()->assertJsonPath('accepted', 1);

    $this->postJson(route('api.ingest.headcount-readings'), $payload, [
        'X-Device-Token' => $plain,
    ])->assertAccepted()->assertJsonPath('duplicates', 1);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'count' => 1,
            'camera_ref' => 'missing-cam',
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('rejected.0.code', 'UNKNOWN_REFERENCE');
});

it('keeps forward-only live headcount per camera', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    [, $plain] = edgeWithToken('edge-hc-03');
    $zone = Zone::factory()->create(['zone_type' => ZoneType::Work]);
    $camera = boundHeadcountCamera($admin, 'CAM-HC-FWD', $zone);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->subMinute()->toIso8601String(),
            'count' => 10,
            'camera_ref' => $camera->reference,
        ]],
    ], ['X-Device-Token' => $plain])->assertAccepted();

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->subMinutes(5)->toIso8601String(),
            'count' => 99,
            'camera_ref' => $camera->reference,
        ]],
    ], ['X-Device-Token' => $plain])->assertAccepted();

    Cache::forget('tracking.headcount');
    Cache::forget('tracking.camera_headcount');

    expect(app(TrackingService::class)->headcountSnapshot()['total_on_site'])->toBe(10);
    expect(CameraHeadcountReading::query()->count())->toBe(2);
});

it('switches Total Manpower to RFID positions without losing camera history', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    [, $plain] = edgeWithToken('edge-hc-04');
    $zone = Zone::factory()->create(['zone_type' => ZoneType::Work]);
    $camera = boundHeadcountCamera($admin, 'CAM-HC-SW', $zone);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'count' => 22,
            'camera_ref' => $camera->reference,
        ]],
    ], ['X-Device-Token' => $plain])->assertAccepted();

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);
    WorkerPosition::query()->where('tag_id', $tag->id)->update(['is_on_site' => true]);

    app(SettingsService::class)->set(
        'tracking.headcount_source',
        HeadcountSource::Rfid->value,
        confirmed: true,
    );

    $snapshot = app(TrackingService::class)->headcountSnapshot();
    expect($snapshot['source'])->toBe('rfid')
        ->and($snapshot['total_on_site'])->toBe(1)
        ->and(CameraHeadcountReading::query()->count())->toBe(1);
});

it('still writes RFID gate entry/exit when live source is camera and does not broadcast HeadcountUpdated', function () {
    Event::fake([HeadcountUpdated::class]);

    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'track-gate-cam-src';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $gate = Zone::factory()->create(['zone_type' => ZoneType::Gate]);
    app(ReaderBindingService::class)->bind($reader, $gate, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'reader_ref' => $reader->reference,
            'tag_uid' => $tag->tag_uid,
            'recorded_at' => now()->toIso8601String(),
        ]],
    ], ['X-Device-Token' => $plain])->assertAccepted();

    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeTrue();
    expect(EntryExitLog::query()->where('worker_id', $worker->id)->where('direction', Direction::In)->count())->toBe(1);
    Event::assertNotDispatched(HeadcountUpdated::class);

    expect(app(TrackingService::class)->headcountSnapshot()['source'])->toBe('camera');
});

it('sums multi-camera counts in headcount flow peak', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    [, $plain] = edgeWithToken('edge-hc-flow');
    $zoneA = Zone::factory()->create(['name' => 'Flow A', 'zone_type' => ZoneType::Work]);
    $zoneB = Zone::factory()->create(['name' => 'Flow B', 'zone_type' => ZoneType::Work]);
    $camA = boundHeadcountCamera($admin, 'CAM-HC-FLOW-A', $zoneA);
    $camB = boundHeadcountCamera($admin, 'CAM-HC-FLOW-B', $zoneB);

    $t1 = now()->subMinutes(5);
    $t2 = now()->subMinutes(2);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => $t1->toIso8601String(),
                'count' => 4,
                'camera_ref' => $camA->reference,
            ],
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => $t1->toIso8601String(),
                'count' => 6,
                'camera_ref' => $camB->reference,
            ],
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => $t2->toIso8601String(),
                'count' => 9,
                'camera_ref' => $camA->reference,
            ],
        ],
    ], ['X-Device-Token' => $plain])->assertAccepted()->assertJsonPath('accepted', 3);

    $flow = app(TrackingService::class)->headcountFlow(
        now()->subMinutes(15),
        now(),
        5,
    );

    expect($flow['peak'])->toBe(15)
        ->and(max($flow['sparkline']))->toBe(15);
});

it('rejects non-edge devices for headcount ingest', function () {
    $reader = Device::factory()->withoutToken()->create([
        'device_type' => DeviceType::RfidReader,
    ]);
    $plain = app(HardwareRegistryService::class)->issueToken($reader)['plain_token'];

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'count' => 1,
            'camera_ref' => 'CAM-ANY',
        ]],
    ], ['X-Device-Token' => $plain])
        ->assertAccepted()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('rejected.0.code', 'WRONG_DEVICE_TYPE');
});

it('stores unbound camera readings with null zone and excludes them from live totals', function () {
    [, $plain] = edgeWithToken('edge-hc-unbound');
    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'reference' => 'CAM-HC-UNBOUND',
    ]);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'count' => 4,
            'camera_ref' => $camera->reference,
        ]],
    ], ['X-Device-Token' => $plain])->assertAccepted()->assertJsonPath('accepted', 1);

    expect(CameraHeadcountReading::query()->where('camera_id', $camera->id)->value('zone_id'))->toBeNull();
    expect(app(TrackingService::class)->headcountSnapshot()['total_on_site'])->toBe(0);
});

it('lists headcount reading records and live API samples', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    [, $plain] = edgeWithToken('edge-hc-ui');
    $zoneA = Zone::factory()->create(['name' => 'UI Bay A', 'zone_type' => ZoneType::Work]);
    $zoneB = Zone::factory()->create(['name' => 'UI Bay B', 'zone_type' => ZoneType::Work]);
    $camA = boundHeadcountCamera($admin, 'CAM-HC-UI-A', $zoneA);
    $camB = boundHeadcountCamera($admin, 'CAM-HC-UI-B', $zoneB);

    $this->postJson(route('api.ingest.headcount-readings'), [
        'events' => [
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => now()->subMinutes(2)->toIso8601String(),
                'count' => 3,
                'camera_ref' => $camA->reference,
            ],
            [
                'event_uid' => (string) Str::uuid(),
                'recorded_at' => now()->subMinute()->toIso8601String(),
                'count' => 8,
                'camera_ref' => $camB->reference,
            ],
        ],
    ], ['X-Device-Token' => $plain])->assertAccepted()->assertJsonPath('accepted', 2);

    $this->actingAs($admin)
        ->getJson(route('tracking.api.headcount-readings'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin)
        ->getJson(route('tracking.api.headcount-readings', ['zone_id' => $zoneA->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.zone_name', 'UI Bay A')
        ->assertJsonPath('data.0.camera_ref', 'CAM-HC-UI-A')
        ->assertJsonPath('data.0.count', 3);

    $this->actingAs($admin)
        ->get(route('tracking.headcount-readings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tracking/headcount-readings/index')
            ->has('readings.data', 2)
            ->where('readings.data.0.count', 8));

    $this->actingAs($admin)
        ->get(route('tracking.headcount-readings.index', [
            'zone_id' => $zoneA->id,
            'camera_id' => $camA->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tracking/headcount-readings/index')
            ->has('readings.data', 1)
            ->where('readings.data.0.camera_ref', 'CAM-HC-UI-A'));
});
