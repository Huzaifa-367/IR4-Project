<?php

use App\Enums\AlertType;
use App\Enums\AlertStatus;
use App\Enums\HardwareStatus;
use App\Enums\ReviewStatus;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Device;
use App\Models\RoiViolation;
use App\Models\User;
use App\Services\Camera\CameraRoiService;
use App\Services\Hardware\HardwareRegistryService;
use Illuminate\Support\Str;

function publishRoiForEdge(Device $edge, User $admin, string $roiRef = 'roi_bay'): Camera
{
    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $edge->id,
        'reference' => 'cam-roi-viol-'.Str::lower(Str::random(4)),
    ]);

    app(CameraRoiService::class)->publish($camera, [[
        'name' => 'Bay',
        'reference' => $roiRef,
        'polygon' => [
            ['x' => 0.1, 'y' => 0.1],
            ['x' => 0.9, 'y' => 0.1],
            ['x' => 0.9, 'y' => 0.9],
            ['x' => 0.1, 'y' => 0.9],
        ],
        'color' => '#22d3ee',
        'sort_order' => 0,
        'is_enabled' => true,
    ]], $admin);

    return $camera->fresh(['roiSet.rois']) ?? $camera;
}

it('ingests an roi intrusion like ppe (camera_ref + device token)', function () {
    $edge = Device::factory()->withoutToken()->create([
        'reference' => 'edge-roi-01',
    ]);
    $plain = app(HardwareRegistryService::class)->issueToken($edge)['plain_token'];
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = publishRoiForEdge($edge, $admin);

    $uid = (string) Str::uuid();

    $this->postJson(route('api.ingest.roi-violations'), [
        'events' => [[
            'event_uid' => $uid,
            'camera_ref' => $camera->reference,
            'roi_reference' => 'roi_bay',
            'event_type' => 'roi_intrusion',
            'detected_at' => now()->toIso8601String(),
            'confidence' => 0.88,
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', 1)
        ->assertJsonPath('duplicates', 0);

    $violation = RoiViolation::query()->where('event_uid', $uid)->first();
    expect($violation)->not->toBeNull()
        ->and($violation->camera_id)->toBe($camera->id)
        ->and($violation->device_id)->toBe($edge->id)
        ->and($violation->roi_reference)->toBe('roi_bay')
        ->and($violation->review_status)->toBe(ReviewStatus::Unreviewed)
        ->and(Alert::query()->where('alert_type', AlertType::RoiViolation)->count())->toBe(1);
});

it('rejects unknown camera_ref and unknown roi like ppe unknown reference', function () {
    $edge = Device::factory()->withoutToken()->create([
        'reference' => 'edge-roi-02',
    ]);
    $plain = app(HardwareRegistryService::class)->issueToken($edge)['plain_token'];
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = publishRoiForEdge($edge, $admin);

    $this->postJson(route('api.ingest.roi-violations'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'camera_ref' => 'missing-camera',
            'roi_reference' => 'roi_bay',
            'event_type' => 'roi_intrusion',
            'detected_at' => now()->toIso8601String(),
            'confidence' => 0.5,
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('rejected.0.code', 'UNKNOWN_REFERENCE');

    $this->postJson(route('api.ingest.roi-violations'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'camera_ref' => $camera->reference,
            'roi_reference' => 'missing_roi',
            'event_type' => 'roi_intrusion',
            'detected_at' => now()->toIso8601String(),
            'confidence' => 0.5,
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('rejected.0.code', 'UNKNOWN_ROI');
});

it('dedupes roi violation event_uid per camera', function () {
    $edge = Device::factory()->withoutToken()->create();
    $plain = app(HardwareRegistryService::class)->issueToken($edge)['plain_token'];
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = publishRoiForEdge($edge, $admin);
    $uid = (string) Str::uuid();
    $payload = [
        'events' => [[
            'event_uid' => $uid,
            'camera_ref' => $camera->reference,
            'roi_reference' => 'roi_bay',
            'event_type' => 'roi_intrusion',
            'detected_at' => now()->toIso8601String(),
            'confidence' => 0.7,
        ]],
    ];

    $this->postJson(route('api.ingest.roi-violations'), $payload, [
        'X-Device-Token' => $plain,
    ])->assertAccepted()->assertJsonPath('accepted', 1);

    $this->postJson(route('api.ingest.roi-violations'), $payload, [
        'X-Device-Token' => $plain,
    ])->assertAccepted()->assertJsonPath('duplicates', 1);

    expect(RoiViolation::query()->where('event_uid', $uid)->count())->toBe(1);
});

it('gates roi violation list and review permissions', function () {
    $edge = Device::factory()->withoutToken()->create();
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = publishRoiForEdge($edge, $admin);

    $violation = RoiViolation::query()->create([
        'camera_id' => $camera->id,
        'camera_roi_id' => $camera->roiSet?->rois->first()?->id,
        'roi_reference' => 'roi_bay',
        'event_type' => 'roi_intrusion',
        'detected_at' => now(),
        'confidence' => 0.9,
        'review_status' => ReviewStatus::Unreviewed,
        'is_backfill' => false,
        'event_uid' => (string) Str::uuid(),
    ]);

    $viewer = User::factory()->withRole('Project Manager')->create();
    $viewer->givePermissionTo('view-roi-violations');

    $this->actingAs($viewer)->get(route('roi-violations.index'))->assertOk();
    $this->actingAs($viewer)
        ->post(route('roi-violations.review', $violation), [
            'status' => 'confirmed',
        ])
        ->assertForbidden();

    $reviewer = User::factory()->withRole('Project Manager')->create();
    $reviewer->givePermissionTo(['view-roi-violations', 'update-roi-violations']);

    $this->actingAs($reviewer)
        ->post(route('roi-violations.review', $violation), [
            'status' => 'confirmed',
            'note' => 'Confirmed after checking the bay camera feed.',
        ])
        ->assertRedirect();

    expect($violation->fresh()->review_status)->toBe(ReviewStatus::Confirmed);
});

it('does not raise alerts for backfill roi events', function () {
    $edge = Device::factory()->withoutToken()->create();
    $plain = app(HardwareRegistryService::class)->issueToken($edge)['plain_token'];
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = publishRoiForEdge($edge, $admin);

    $this->postJson(route('api.ingest.roi-violations'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'camera_ref' => $camera->reference,
            'roi_reference' => 'roi_bay',
            'event_type' => 'roi_intrusion',
            'detected_at' => now()->subMinutes(30)->toIso8601String(),
            'confidence' => 0.7,
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $violation = RoiViolation::query()->first();
    expect($violation?->is_backfill)->toBeTrue()
        ->and($violation?->alert_id)->toBeNull()
        ->and(Alert::query()->where('alert_type', AlertType::RoiViolation)->count())->toBe(0);
});

it('false-positive review resolves the linked roi alert', function () {
    $edge = Device::factory()->withoutToken()->create();
    $plain = app(HardwareRegistryService::class)->issueToken($edge)['plain_token'];
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = publishRoiForEdge($edge, $admin);

    $this->postJson(route('api.ingest.roi-violations'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'camera_ref' => $camera->reference,
            'roi_reference' => 'roi_bay',
            'event_type' => 'roi_intrusion',
            'detected_at' => now()->toIso8601String(),
            'confidence' => 0.9,
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])->assertAccepted();

    $violation = RoiViolation::query()->firstOrFail();
    $alertId = $violation->alert_id;

    $this->actingAs($admin)
        ->post(route('roi-violations.review', $violation), [
            'status' => 'false_positive',
            'note' => 'Reflection on the bay floor',
        ])
        ->assertRedirect();

    expect($violation->fresh()->review_status)->toBe(ReviewStatus::FalsePositive)
        ->and(Alert::query()->find($alertId)?->status)->toBe(AlertStatus::Resolved);
});
