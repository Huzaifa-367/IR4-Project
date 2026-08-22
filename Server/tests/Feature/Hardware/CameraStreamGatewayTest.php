<?php

use App\Models\Asset;
use App\Models\Camera;
use App\Models\User;
use App\Services\Camera\CameraStreamGatewayService;
use App\Services\HardwareRegistryService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(CameraStreamGatewayService::class)->forgetResolvedApiBase();
});

it('pushes camera rtsp to mediamtx when api url is configured', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://mediamtx.test:9997');
    config()->set('camera_stream.mediamtx.source_on_demand', true);

    Http::fake([
        'mediamtx.test:9997/v3/config/paths/replace/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $camera = Camera::factory()->create([
        'reference' => 'cam-gate-01',
        'stream_url' => 'rtsp://10.0.0.21/stream1',
    ]);

    app(CameraStreamGatewayService::class)->sync($camera);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://mediamtx.test:9997/v3/config/paths/replace/cam-gate-01'
            && $request['source'] === 'rtsp://10.0.0.21/stream1'
            && $request['sourceOnDemand'] === true;
    });
});

it('adds mediamtx path when replace fails then add succeeds', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://mediamtx.test:9997');

    Http::fake([
        'mediamtx.test:9997/v3/config/paths/replace/*' => Http::response(['error' => 'not found'], 404),
        'mediamtx.test:9997/v3/config/paths/add/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $camera = Camera::factory()->create([
        'reference' => 'cam-new',
        'stream_url' => 'rtsp://10.0.0.9/live',
    ]);

    app(CameraStreamGatewayService::class)->sync($camera);

    Http::assertSentCount(2);
});

it('syncs through hardware registry on camera create', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://mediamtx.test:9997');
    Http::fake([
        'mediamtx.test:9997/v3/config/paths/replace/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $admin = User::factory()->withRole('Super Admin')->create();
    $asset = Asset::factory()->create();

    $this->actingAs($admin)
        ->post(route('settings.cameras.store'), [
            'asset_id' => $asset->id,
            'name' => 'Gate Cam',
            'reference' => 'cam-auto-1',
            'camera_type' => 'fixed',
            'stream_url' => 'rtsp://10.0.0.55/stream1',
        ])
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'cam-auto-1')
        && ($request['source'] ?? null) === 'rtsp://10.0.0.55/stream1');
});

it('skips sync when mediamtx api is not configured', function () {
    config()->set('camera_stream.mediamtx.api_url', '');
    Http::fake();

    app(HardwareRegistryService::class)->createCamera([
        'asset_id' => Asset::factory()->create()->id,
        'name' => 'Offline Cam',
        'reference' => 'cam-skip',
        'camera_type' => 'fixed',
        'stream_url' => 'rtsp://10.0.0.1/x',
    ]);

    Http::assertNothingSent();
});

it('encodes rtsp passwords that contain @ before pushing to mediamtx', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://mediamtx.test:9997');
    Http::fake([
        'mediamtx.test:9997/v3/config/paths/replace/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $camera = Camera::factory()->create([
        'reference' => 'cam-ppe-01',
        'stream_url' => 'rtsp://admin:UNity@320@@192.168.1.64:554/Streaming/Channels/101',
    ]);

    $gateway = app(CameraStreamGatewayService::class);
    expect($gateway->encodeRtspSource($camera->stream_url))
        ->toBe('rtsp://admin:UNity%40320%40@192.168.1.64:554/Streaming/Channels/101');

    $gateway->sync($camera);

    Http::assertSent(fn ($request) => ($request['source'] ?? null)
        === 'rtsp://admin:UNity%40320%40@192.168.1.64:554/Streaming/Channels/101');
});

it('reports failed syncs when mediamtx is unreachable', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://mediamtx.test:9997');
    Http::fake([
        'mediamtx.test:9997/*' => Http::response(['error' => 'no'], 500),
    ]);

    Camera::factory()->create([
        'reference' => 'cam-fail',
        'stream_url' => 'rtsp://10.0.0.1/x',
    ]);

    $result = app(CameraStreamGatewayService::class)->syncAll();

    expect($result['synced'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'])->toContain('cam-fail');
});

it('probes mediamtx api reachability', function () {
    config()->set('camera_stream.mediamtx.api_url', 'http://mediamtx.test:9997');
    Http::fake([
        'mediamtx.test:9997/v3/config/paths/list' => Http::response(['itemCount' => 0, 'items' => []], 200),
    ]);

    $probe = app(CameraStreamGatewayService::class)->probe();

    expect($probe['ok'])->toBeTrue()
        ->and($probe['status'])->toBe(200);
});

it('resolves gateway api url preferring MEDIAMTX_HOST_IP', function () {
    config()->set('camera_stream.mediamtx.api_url', 'gateway');
    config()->set('camera_stream.mediamtx.host_ip', '192.168.3.149');
    Http::fake(function ($request) {
        if (str_contains($request->url(), '192.168.3.149:9997')) {
            return Http::response(['itemCount' => 0, 'items' => []], 200);
        }

        return Http::response('unreachable', 500);
    });

    $gateway = app(CameraStreamGatewayService::class);

    expect($gateway->apiBaseUrl())->toBe('http://192.168.3.149:9997');
});
