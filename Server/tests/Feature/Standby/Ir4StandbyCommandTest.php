<?php

use App\Console\Commands\StandbyPolesCommand;
use App\Support\EdgeDeviceCredentials;
use App\Support\StandbyPoleIngest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('posts a one-shot helmet violation without a snapshot field', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'h',
        'pole' => '1',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $event = $request['events'][0] ?? [];

        return str_contains($request->url(), '/api/ingest/ppe-violations')
            && ($event['event_type'] ?? null) === 'missing_helmet'
            && ($event['camera_ref'] ?? null) === 'CAM-FIXED-01'
            && ! array_key_exists('snapshot', $event)
            && $request->header('X-Device-Token')[0] === (EdgeDeviceCredentials::find('DEV-CAM-FIXED-01')['token'] ?? '');
    });
});

it('defaults rfid tag to W0001', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'r',
        'pole' => '2',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $event = $request['events'][0] ?? [];

        return str_contains($request->url(), '/api/ingest/tag-readings')
            && ($event['reader_ref'] ?? null) === 'DEV-RFID-02'
            && ($event['tag_uid'] ?? null) === 'E280116060000203IR4W0001';
    });
});

it('keeps long device names as aliases', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'rfid',
        'pole' => '1',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $this->artisan('ir4:s', [
        'action' => 'helmet',
        'pole' => '1',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/ingest/tag-readings'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/ingest/ppe-violations'));
});

it('rejects gate rfid', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'r',
        'pole' => 'g',
        '--url' => 'http://standby.test',
    ])->assertFailed();

    Http::assertNothingSent();
});

it('honours --url for device API base', function () {
    config()->set('app.url', 'http://laravel.test:8000');
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'g',
        'pole' => '1',
        '--alarm' => true,
        '--url' => 'http://edge-base.test:9100',
    ])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'http://edge-base.test:9100/api/ingest/gas-readings'));
});

it('falls back to APP_URL when no override is set', function () {
    config()->set('app.url', 'http://laravel.test:8000');
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'g',
        'pole' => '1',
        '--alarm' => true,
    ])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'http://laravel.test:8000/api/ingest/gas-readings'));
});

it('rejects empty 204 responses that are not ir4 ingest', function () {
    Http::fake(['*' => Http::response('', 204)]);

    $client = new StandbyPoleIngest('http://flutter.test:9100');

    expect(fn () => $client->postGasReadings('tok', []))
        ->toThrow(RuntimeException::class, 'same IR4 device APIs');
});

it('posts g --alarm above seeded warning thresholds', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'g',
        'pole' => '1',
        '--alarm' => true,
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $event = $request['events'][0] ?? [];

        return str_contains($request->url(), '/api/ingest/gas-readings')
            && (float) ($event['h2s_ppm'] ?? 0) >= 5.0
            && (float) ($event['co_ppm'] ?? 0) >= 25.0
            && (float) ($event['lel_pct'] ?? 0) >= 10.0
            && (float) ($event['co2_ppm'] ?? 0) >= 5000.0;
    });
});

it('lets t skip ambient gas while an alarm hold is active', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/heartbeat')) {
            return Http::response(['data' => ['status' => 'online']], 200);
        }

        return Http::response(['accepted' => 1], 202);
    });
    Cache::put('ir4:standby:gas-alarm:1', true, now()->addMinutes(1));

    $this->artisan('ir4:s', [
        'action' => 't',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $gasRefs = [];
    Http::assertSent(function ($request) use (&$gasRefs): bool {
        if (! str_contains($request->url(), '/api/ingest/gas-readings')) {
            return false;
        }
        $gasRefs[] = $request['events'][0]['device_ref'] ?? '';

        return true;
    });

    expect($gasRefs)->not->toContain('DEV-GAS-01')
        ->and($gasRefs)->toContain('DEV-GAS-02');
});

it('lets t skip ambient gas while a per-pole ambient g --loop hold is active', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/heartbeat')) {
            return Http::response(['data' => ['status' => 'online']], 200);
        }

        return Http::response(['accepted' => 1], 202);
    });
    Cache::put('ir4:standby:gas-ambient:2', true, now()->addMinutes(1));

    $this->artisan('ir4:s', [
        'action' => 't',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $gasRefs = [];
    Http::assertSent(function ($request) use (&$gasRefs): bool {
        if (! str_contains($request->url(), '/api/ingest/gas-readings')) {
            return false;
        }
        $gasRefs[] = $request['events'][0]['device_ref'] ?? '';

        return true;
    });

    expect($gasRefs)->not->toContain('DEV-GAS-02')
        ->and($gasRefs)->toContain('DEV-GAS-01');
});

it('documents device letter commands on the signature', function () {
    $command = app(StandbyPolesCommand::class);

    expect($command->getDefinition()->hasOption('alarm'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('loop'))->toBeTrue()
        ->and($command->getDefinition()->getArgument('action')->getDescription())
        ->toContain('t|g|r|h|v');
});
