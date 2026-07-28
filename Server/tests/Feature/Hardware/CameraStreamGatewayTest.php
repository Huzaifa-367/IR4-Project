<?php

use App\Models\Camera;
use App\Models\User;
use App\Services\CameraStreamGatewayService;
use App\Services\HardwareRegistryService;
use Illuminate\Support\Facades\Http;

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
    $asset = \App\Models\Asset::factory()->create();

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
        'asset_id' => \App\Models\Asset::factory()->create()->id,
        'name' => 'Offline Cam',
        'reference' => 'cam-skip',
        'camera_type' => 'fixed',
        'stream_url' => 'rtsp://10.0.0.1/x',
    ]);

    Http::assertNothingSent();
});
