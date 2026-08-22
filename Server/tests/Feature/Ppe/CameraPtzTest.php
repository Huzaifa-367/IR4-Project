<?php

use App\Enums\AuditEvent;
use App\Enums\CameraType;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\User;
use App\Support\RtspStreamEndpoint;
use Illuminate\Support\Facades\Http;

it('requires control-ptz-cameras permission', function () {
    $camera = Camera::factory()->create([
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $viewer = User::factory()->withRole('Project Manager')->create();
    $viewer->givePermissionTo('view-live-cameras');

    $this->actingAs($viewer)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'stop',
        ])
        ->assertForbidden();
});

it('rejects ptz on fixed cameras', function () {
    $camera = Camera::factory()->create([
        'camera_type' => CameraType::Fixed,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo(['view-live-cameras', 'control-ptz-cameras']);

    $this->actingAs($operator)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'stop',
        ])
        ->assertForbidden();
});

it('proxies each ptz click to a short continuous burst', function (string $label, int $pan, int $tilt, int $zoom, int $speedPan, int $speedTilt, int $speedZoom) {
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

    $camera = Camera::factory()->create([
        'reference' => 'CAM-PTZ-VECTORS',
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo(['view-live-cameras', 'control-ptz-cameras']);

    $this->actingAs($operator)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'move',
            'pan' => $pan,
            'tilt' => $tilt,
            'zoom' => $zoom,
        ])
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    Http::assertSent(function ($request) use ($speedPan, $speedTilt, $speedZoom) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/ISAPI/PTZCtrl/channels/1/continuous')
            && str_contains($request->body(), '<pan>'.$speedPan.'</pan>')
            && str_contains($request->body(), '<tilt>'.$speedTilt.'</tilt>')
            && str_contains($request->body(), '<zoom>'.$speedZoom.'</zoom>');
    });
})->with([
    'pan left' => ['left', -30, 0, 0, -35, 0, 0],
    'pan right' => ['right', 30, 0, 0, 35, 0, 0],
    'tilt up' => ['up', 0, 30, 0, 0, 35, 0],
    'tilt down' => ['down', 0, -30, 0, 0, -35, 0],
    'zoom in' => ['zoom-in', 0, 0, 1, 0, 0, 35],
    'zoom out' => ['zoom-out', 0, 0, -1, 0, 0, -35],
]);

it('audits each click nudge', function () {
    Http::fake(function ($request) {
        if (! str_contains($request->url(), '172.16.1.10')) {
            return Http::response('not found', 404);
        }

        return Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0"><requestURL>/ISAPI/PTZCtrl/channels/1/continuous</requestURL><statusCode>1</statusCode><statusString>OK</statusString></ResponseStatus>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    });

    $camera = Camera::factory()->create([
        'reference' => 'CAM-PTZ-TEST',
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:Unity@320@@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo(['view-live-cameras', 'control-ptz-cameras']);

    $before = AuditLog::query()->count();

    $this->actingAs($operator)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'move',
            'pan' => 30,
            'tilt' => -30,
            'zoom' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/ISAPI/PTZCtrl/channels/1/continuous')
            && str_contains($request->body(), '<pan>35</pan>')
            && str_contains($request->body(), '<tilt>-35</tilt>');
    });

    expect(AuditLog::query()->count())->toBe($before + 1);
});

it('uses the isapi stop endpoint and audits stop commands', function () {
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

    $camera = Camera::factory()->create([
        'reference' => 'CAM-PTZ-STOP',
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo(['view-live-cameras', 'control-ptz-cameras']);

    $this->actingAs($operator)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'stop',
        ])
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/ISAPI/PTZCtrl/channels/1/continuous')
        && str_contains($request->body(), '<pan>0</pan>'));

    expect(AuditLog::query()
        ->where('event', AuditEvent::ConfigChanged)
        ->where('auditable_type', $camera->getMorphClass())
        ->where('auditable_id', $camera->id)
        ->where('new_values->command', 'stop')
        ->exists())->toBeTrue();
});

it('treats an idle stop response as success', function () {
    Http::fake(function ($request) {
        if (! str_contains($request->url(), '172.16.1.10')) {
            return Http::response('not found', 404);
        }

        return Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0"><statusCode>4</statusCode><statusString>Invalid Operation</statusString></ResponseStatus>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    });

    $camera = Camera::factory()->create([
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo(['view-live-cameras', 'control-ptz-cameras']);

    $this->actingAs($operator)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'stop',
        ])
        ->assertOk()
        ->assertJsonPath('data.accepted', true);
});

it('rejects isapi responses whose statusCode is not ok', function () {
    Http::fake(function ($request) {
        if (! str_contains($request->url(), '172.16.1.10')) {
            return Http::response('not found', 404);
        }

        return Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0"><statusCode>4</statusCode><statusString>Invalid Operation</statusString></ResponseStatus>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    });

    $camera = Camera::factory()->create([
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo(['view-live-cameras', 'control-ptz-cameras']);

    $this->actingAs($operator)
        ->postJson(route('live.cameras.ptz', $camera), [
            'action' => 'move',
            'pan' => 10,
            'tilt' => 0,
            'zoom' => 0,
        ])
        ->assertStatus(502)
        ->assertJsonPath('error.code', 'ptz_failed');
});

it('includes ptz flags on live wall camera rows', function () {
    $ptz = Camera::factory()->create([
        'camera_type' => CameraType::Ptz,
        'stream_url' => 'rtsp://admin:secret@172.16.1.10:554/Streaming/Channels/101',
    ]);
    Camera::factory()->create([
        'camera_type' => CameraType::Fixed,
    ]);

    $operator = User::factory()->withRole('SCC Operator')->create();

    $response = $this->actingAs($operator)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('live/index')
            ->where('canControlPtz', true)
            ->has('cameras', 2));

    $cameras = collect($response->original->getData()['page']['props']['cameras']);
    $ptzRow = $cameras->firstWhere('uuid', $ptz->uuid);

    expect($ptzRow)->not->toBeNull()
        ->and($ptzRow['is_ptz'])->toBeTrue()
        ->and($ptzRow['can_control_ptz'])->toBeTrue();
});

it('parses rtsp passwords containing at signs', function () {
    $endpoint = RtspStreamEndpoint::fromCamera(new Camera([
        'stream_url' => 'rtsp://admin:Unity@320@@172.16.3.10:554/Streaming/Channels/101',
        'meta' => null,
    ]));

    expect($endpoint)->not->toBeNull()
        ->and($endpoint->host)->toBe('172.16.3.10')
        ->and($endpoint->username)->toBe('admin')
        ->and($endpoint->password)->toBe('Unity@320@')
        ->and($endpoint->channelId)->toBe(1);
});
