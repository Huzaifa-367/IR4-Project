<?php

use App\Enums\CameraRoiSetStatus;
use App\Enums\CameraRoiStaleReason;
use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Camera;
use App\Models\Device;
use App\Models\User;
use App\Services\Camera\CameraRoiService;
use App\Services\Hardware\HardwareRegistryService;
use Illuminate\Support\Facades\Http;

function validRoiPayload(string $name = 'Work bay', string $reference = 'roi_work_bay'): array
{
    return [
        'name' => $name,
        'reference' => $reference,
        'polygon' => [
            ['x' => 0.1, 'y' => 0.1],
            ['x' => 0.9, 'y' => 0.1],
            ['x' => 0.9, 'y' => 0.9],
            ['x' => 0.1, 'y' => 0.9],
        ],
        'color' => '#22d3ee',
        'sort_order' => 0,
        'is_enabled' => true,
    ];
}

it('rejects polygons with fewer than three points', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create(['status' => HardwareStatus::Online]);

    $this->actingAs($admin)
        ->put(route('hardware.camera-rois.update', $camera), [
            'rois' => [[
                'name' => 'Too small',
                'reference' => 'roi_small',
                'polygon' => [
                    ['x' => 0.1, 'y' => 0.1],
                    ['x' => 0.2, 'y' => 0.2],
                ],
            ]],
        ])
        ->assertSessionHasErrors('rois.0.polygon');
});

it('rejects coordinates outside the 0-1 normalized range', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create(['status' => HardwareStatus::Online]);

    $this->actingAs($admin)
        ->put(route('hardware.camera-rois.update', $camera), [
            'rois' => [[
                'name' => 'OOB',
                'reference' => 'roi_oob',
                'polygon' => [
                    ['x' => 0.0, 'y' => 0.0],
                    ['x' => 1.5, 'y' => 0.0],
                    ['x' => 0.5, 'y' => 1.0],
                ],
            ]],
        ])
        ->assertSessionHasErrors('rois.0.polygon.1.x');
});

it('saves a draft and publishes an active set for edge sync', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create(['status' => HardwareStatus::Online]);

    $this->actingAs($admin)
        ->put(route('hardware.camera-rois.update', $camera), [
            'rois' => [validRoiPayload()],
        ])
        ->assertRedirect(route('hardware.camera-rois.edit', $camera));

    $camera->refresh()->load('roiSet.rois');
    expect($camera->roiSet)->not->toBeNull()
        ->and($camera->roiSet->status)->toBe(CameraRoiSetStatus::Draft)
        ->and($camera->roiSet->rois)->toHaveCount(1);

    $this->actingAs($admin)
        ->post(route('hardware.camera-rois.publish', $camera), [
            'rois' => [validRoiPayload()],
        ])
        ->assertRedirect(route('hardware.camera-rois.edit', $camera));

    $camera->refresh()->load('roiSet');
    expect($camera->roiSet->status)->toBe(CameraRoiSetStatus::Active)
        ->and($camera->roiSet->published_at)->not->toBeNull()
        ->and($camera->roiSet->stale_reason)->toBeNull();
});

it('marks the set stale after a successful ptz move', function () {
    Http::fake(function ($request) {
        if (! str_contains($request->url(), '172.16.1.10')) {
            return Http::response('not found', 404);
        }

        return Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0"><statusCode>1</statusCode><statusString>OK</statusString></ResponseStatus>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    });

    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create([
        'camera_type' => CameraType::Ptz,
        'status' => HardwareStatus::Online,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
        'ptz_generation' => 0,
    ]);

    app(CameraRoiService::class)->publish($camera, [validRoiPayload()], $admin);
    expect($camera->fresh()->roiSet->status)->toBe(CameraRoiSetStatus::Active);

    $this->actingAs($admin)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'move',
            'pan' => 1,
            'tilt' => 0,
            'zoom' => 0,
        ])
        ->assertOk();

    $camera->refresh()->load('roiSet');
    expect($camera->ptz_generation)->toBe(1)
        ->and($camera->roiSet->status)->toBe(CameraRoiSetStatus::Stale)
        ->and($camera->roiSet->stale_reason)->toBe(CameraRoiStaleReason::Ptz);
});

it('does not mark rois stale on ptz stop', function () {
    Http::fake(function ($request) {
        if (! str_contains($request->url(), '172.16.1.10')) {
            return Http::response('not found', 404);
        }

        return Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0"><statusCode>1</statusCode><statusString>OK</statusString></ResponseStatus>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    });

    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create([
        'camera_type' => CameraType::Ptz,
        'status' => HardwareStatus::Online,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
        'ptz_generation' => 0,
    ]);

    app(CameraRoiService::class)->publish($camera, [validRoiPayload()], $admin);

    $this->actingAs($admin)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'stop',
        ])
        ->assertOk();

    $camera->refresh()->load('roiSet');
    expect($camera->ptz_generation)->toBe(0)
        ->and($camera->roiSet->status)->toBe(CameraRoiSetStatus::Active);
});

it('marks the set stale when stream_url changes', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'stream_url' => 'rtsp://10.0.0.5/stream1',
    ]);

    app(CameraRoiService::class)->publish($camera, [validRoiPayload()], $admin);

    app(HardwareRegistryService::class)->updateCamera($camera, [
        'name' => $camera->name,
        'reference' => $camera->reference,
        'camera_type' => $camera->camera_type->value,
        'stream_url' => 'rtsp://10.0.0.5/stream2',
        'ai_enabled' => $camera->ai_enabled,
        'asset_id' => $camera->asset_id,
        'processed_by_device_id' => $camera->processed_by_device_id,
    ]);

    $camera->refresh()->load('roiSet');
    expect($camera->roiSet->status)->toBe(CameraRoiSetStatus::Stale)
        ->and($camera->roiSet->stale_reason)->toBe(CameraRoiStaleReason::StreamChanged);
});

it('returns active rois for the authenticated device camera only', function () {
    $device = Device::factory()->withoutToken()->create();
    $plain = app(HardwareRegistryService::class)->issueToken($device)['plain_token'];

    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $device->id,
    ]);

    $admin = User::factory()->withRole('Super Admin')->create();
    app(CameraRoiService::class)->publish($camera, [validRoiPayload('Active', 'roi_active')], $admin);

    $this->getJson(route('api.devices.camera-rois', $device), [
        'X-Device-Token' => $plain,
    ])
        ->assertOk()
        ->assertJsonPath('data.device.id', $device->id)
        ->assertJsonPath('data.device.uuid', $device->uuid)
        ->assertJsonPath('data.device.reference', $device->reference)
        ->assertJsonPath('data.rois.0.reference', 'roi_active')
        ->assertJsonMissingPath('data.cameras')
        ->assertJsonMissingPath('data.device.token')
        ->assertJsonMissingPath('data.token');

    app(CameraRoiService::class)->markStale($camera, CameraRoiStaleReason::Manual, $admin);

    $this->getJson(route('api.devices.camera-rois', $device), [
        'X-Device-Token' => $plain,
    ])
        ->assertOk()
        ->assertJsonCount(0, 'data.rois')
        ->assertJsonPath('data.view_fingerprint', null);
});

it('scopes camera-rois pull to the device uuid and rejects token mismatch', function () {
    $device = Device::factory()->withoutToken()->create();
    $other = Device::factory()->withoutToken()->create();
    $plain = app(HardwareRegistryService::class)->issueToken($device)['plain_token'];
    $otherPlain = app(HardwareRegistryService::class)->issueToken($other)['plain_token'];
    $admin = User::factory()->withRole('Super Admin')->create();

    $owned = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $device->id,
    ]);
    $foreign = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $other->id,
    ]);

    app(CameraRoiService::class)->publish($owned, [validRoiPayload('Keep', 'roi_keep')], $admin);
    app(CameraRoiService::class)->publish($foreign, [validRoiPayload('Foreign', 'roi_foreign')], $admin);

    $this->getJson(route('api.devices.camera-rois', $device), [
        'X-Device-Token' => $plain,
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.rois')
        ->assertJsonPath('data.rois.0.reference', 'roi_keep');

    $this->getJson(route('api.devices.camera-rois', $other), [
        'X-Device-Token' => $plain,
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    $this->getJson(route('api.devices.camera-rois', $other), [
        'X-Device-Token' => $otherPlain,
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.rois')
        ->assertJsonPath('data.rois.0.reference', 'roi_foreign');
});

it('lists only online cameras on the roi index', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $online = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'name' => 'Online Cam',
        'last_frame_at' => now(),
    ]);
    Camera::factory()->create([
        'status' => HardwareStatus::Offline,
        'name' => 'Offline Cam',
        'last_frame_at' => null,
    ]);
    Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'name' => 'Stale Cam',
        'last_frame_at' => now()->subHours(2),
    ]);

    $this->actingAs($admin)
        ->get(route('hardware.camera-rois.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('camera-rois/index')
            ->has('cameras', 1)
            ->where('cameras.0.uuid', $online->uuid));
});

it('gates view and manage camera-rois permissions', function () {
    $camera = Camera::factory()->create(['status' => HardwareStatus::Online]);
    $viewer = User::factory()->withRole('Project Manager')->create();
    $viewer->givePermissionTo('view-camera-rois');

    $this->actingAs($viewer)->get(route('hardware.camera-rois.index'))->assertOk();
    $this->actingAs($viewer)->get(route('hardware.camera-rois.edit', $camera))->assertOk();
    $this->actingAs($viewer)
        ->putJson(route('hardware.camera-rois.update', $camera), [
            'rois' => [validRoiPayload()],
        ])
        ->assertForbidden();

    $manager = User::factory()->withRole('Project Manager')->create();
    $manager->givePermissionTo(['view-camera-rois', 'manage-camera-rois']);

    $this->actingAs($manager)
        ->put(route('hardware.camera-rois.update', $camera), [
            'rois' => [validRoiPayload()],
        ])
        ->assertRedirect();

    expect($camera->fresh()->roiSet)->not->toBeNull();
});

it('includes draft active and stale roi overlays on the live wall', function () {
    config()->set('camera_stream.browser_url_template', '/hls/{reference}/');
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'reference' => 'cam-roi-live',
        'last_frame_at' => now(),
    ]);

    app(CameraRoiService::class)->saveDraft($camera, [validRoiPayload()], $admin);

    $this->actingAs($admin)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cameras.0.roi_overlay.status', 'draft')
            ->where('cameras.0.roi_overlay.rois.0.reference', 'roi_work_bay'));

    app(CameraRoiService::class)->publish($camera, null, $admin);

    $this->actingAs($admin)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cameras.0.roi_overlay.status', 'active')
            ->where('cameras.0.roi_overlay.rois.0.reference', 'roi_work_bay'));

    app(CameraRoiService::class)->markStale($camera, CameraRoiStaleReason::Manual, $admin);

    $this->actingAs($admin)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cameras.0.roi_overlay.status', 'stale')
            ->where('cameras.0.roi_overlay.stale_reason', 'manual'));
});

it('blocks publish without at least one roi', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create(['status' => HardwareStatus::Online]);

    $this->actingAs($admin)
        ->post(route('hardware.camera-rois.publish', $camera), [
            'rois' => [],
        ])
        ->assertSessionHasErrors('rois');
});

it('blocks publish when every roi is disabled', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create(['status' => HardwareStatus::Online]);
    $disabled = validRoiPayload();
    $disabled['is_enabled'] = false;

    $this->actingAs($admin)
        ->post(route('hardware.camera-rois.publish', $camera), [
            'rois' => [$disabled],
        ])
        ->assertSessionHasErrors('rois');
});

it('pushes the get payload to the jetson ai host on publish', function () {
    Http::fake([
        'http://172.16.3.2:8600/rois' => Http::response(['ok' => true], 200),
    ]);

    $device = Device::factory()->withoutToken()->create([
        'reference' => 'DEV-CAM-AI-PUSH',
        'device_type' => DeviceType::EdgeCompute,
        'config' => ['api_url' => 'http://172.16.3.2:8600/rois'],
    ]);
    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $device->id,
    ]);
    $admin = User::factory()->withRole('Super Admin')->create();

    app(CameraRoiService::class)->publish($camera, [validRoiPayload('Bay', 'roi_bay')], $admin);

    Http::assertSent(function ($request) use ($device): bool {
        if ($request->url() !== 'http://172.16.3.2:8600/rois' || $request->method() !== 'POST') {
            return false;
        }
        $body = $request->data();

        return ($body['device']['uuid'] ?? null) === $device->uuid
            && ($body['device']['reference'] ?? null) === $device->reference
            && ($body['rois'][0]['reference'] ?? null) === 'roi_bay'
            && isset($body['view_fingerprint']);
    });
});

it('pushes each pole camera to its own device uuid even when api_url is shared', function () {
    Http::fake([
        'http://172.16.3.2:8600/rois' => Http::response(['ok' => true], 200),
    ]);

    $fixedDev = Device::factory()->withoutToken()->create([
        'reference' => 'DEV-CAM-FIXED-X',
        'device_type' => DeviceType::EdgeCompute,
        'config' => ['api_url' => 'http://172.16.3.2:8600/rois', 'camera_ref' => 'CAM-FIXED-X'],
    ]);
    $ptzDev = Device::factory()->withoutToken()->create([
        'reference' => 'DEV-CAM-PTZ-X',
        'device_type' => DeviceType::EdgeCompute,
        'config' => ['api_url' => 'http://172.16.3.2:8600/rois', 'camera_ref' => 'CAM-PTZ-X'],
    ]);
    $fixedCam = Camera::factory()->create([
        'reference' => 'CAM-FIXED-X',
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $fixedDev->id,
    ]);
    $ptzCam = Camera::factory()->create([
        'reference' => 'CAM-PTZ-X',
        'status' => HardwareStatus::Online,
        'processed_by_device_id' => $ptzDev->id,
    ]);
    $admin = User::factory()->withRole('Super Admin')->create();

    app(CameraRoiService::class)->publish($fixedCam, [validRoiPayload('Fixed ROI', 'roi_fixed')], $admin);
    app(CameraRoiService::class)->publish($ptzCam, [validRoiPayload('Ptz ROI', 'roi_ptz')], $admin);

    Http::assertSent(fn ($r): bool => ($r->data()['device']['uuid'] ?? null) === $fixedDev->uuid
        && ($r->data()['rois'][0]['reference'] ?? null) === 'roi_fixed');
    Http::assertSent(fn ($r): bool => ($r->data()['device']['uuid'] ?? null) === $ptzDev->uuid
        && ($r->data()['rois'][0]['reference'] ?? null) === 'roi_ptz');
});

it('scrubs legacy ai_host / ai_base_url keys when updating a device', function () {
    $device = Device::factory()->withoutToken()->create([
        'device_type' => DeviceType::EdgeCompute,
        'config' => [
            'ai_host' => '172.16.3.2',
            'ai_base_url' => 'http://172.16.3.2:8600',
            'camera_ref' => 'CAM-LEGACY',
        ],
    ]);

    $updated = app(HardwareRegistryService::class)->updateDevice($device, [
        'api_url' => 'http://172.16.3.2:8600/rois',
    ]);

    expect($updated->config)->toMatchArray([
        'api_url' => 'http://172.16.3.2:8600/rois',
        'camera_ref' => 'CAM-LEGACY',
    ])
        ->and($updated->config)->not->toHaveKey('ai_host')
        ->and($updated->config)->not->toHaveKey('ai_base_url');
});
