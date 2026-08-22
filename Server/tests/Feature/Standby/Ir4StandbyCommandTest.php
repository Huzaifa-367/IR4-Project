<?php

use App\Console\Commands\StandbyPolesCommand;
use App\Models\Device;
use App\Models\GasReading;
use App\Support\EdgeDeviceCredentials;
use App\Support\SiteRfidTags;
use App\Support\StandbyPoleIngest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('posts working-at-heights and fall as ppe ingest types', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'w',
        'pole' => '4',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $this->artisan('ir4:s', [
        'action' => 'f',
        'pole' => '4',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $types = [];
    Http::assertSent(function ($request) use (&$types): bool {
        if (! str_contains($request->url(), '/api/ingest/ppe-violations')) {
            return false;
        }
        $types[] = $request['events'][0]['event_type'] ?? '';

        return true;
    });

    expect($types)->toBe(['missing_harness', 'fall']);
});

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

it('defaults rfid tag to the first site EPC', function () {
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
            && ($event['tag_uid'] ?? null) === SiteRfidTags::at(1);
    });
});

it('maps a numeric rfid argument to the matching site EPC', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'r',
        'pole' => '2',
        'tag' => '3',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $event = $request['events'][0] ?? [];

        return str_contains($request->url(), '/api/ingest/tag-readings')
            && ($event['tag_uid'] ?? null) === SiteRfidTags::at(3);
    });
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

it('posts heartbeats only on t with no gas ingest', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/heartbeat')) {
            return Http::response(['data' => ['status' => 'online']], 200);
        }

        return Http::response(['accepted' => 1], 202);
    });

    $this->artisan('ir4:s', [
        'action' => 't',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/heartbeat'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/api/ingest/gas-readings'));
});

it('posts ambient gas for all poles with g all', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'g',
        'pole' => 'all',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $refs = [];
    Http::assertSent(function ($request) use (&$refs): bool {
        if (! str_contains($request->url(), '/api/ingest/gas-readings')) {
            return false;
        }
        $refs[] = $request['events'][0]['device_ref'] ?? '';

        return true;
    });

    expect($refs)->toBe(['DEV-GAS-01', 'DEV-GAS-02', 'DEV-GAS-03', 'DEV-GAS-04']);
});

it('requires pole or all for g', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'g',
        '--url' => 'http://standby.test',
    ])->assertFailed();

    Http::assertNothingSent();
});

it('lets g all skip a pole owned by single-pole g loop', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);
    Cache::put('ir4:standby:gas-ambient:2', true, now()->addMinutes(1));

    $this->artisan('ir4:s', [
        'action' => 'g',
        'pole' => 'all',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $refs = [];
    Http::assertSent(function ($request) use (&$refs): bool {
        if (! str_contains($request->url(), '/api/ingest/gas-readings')) {
            return false;
        }
        $refs[] = $request['events'][0]['device_ref'] ?? '';

        return true;
    });

    expect($refs)->not->toContain('DEV-GAS-02')
        ->and($refs)->toContain('DEV-GAS-01')
        ->and($refs)->toContain('DEV-GAS-03');
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

it('documents device letter commands on the signature', function () {
    $command = app(StandbyPolesCommand::class);

    expect($command->getDefinition()->hasOption('alarm'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('loop'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('to'))->toBeTrue()
        ->and($command->getDefinition()->getArgument('action')->getDescription())
        ->toContain('t|g|m|r|h|v|w|f|k')
        ->and($command->getDefinition()->getArgument('pole')->getDescription())
        ->toContain('all')
        ->and($command->getDefinition()->getArgument('tag')->getDescription())
        ->toContain('EPC');
});

it('mimics the latest source-pole gas reading onto other poles', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $source = Device::factory()->gasDetector()->create([
        'reference' => 'DEV-GAS-02',
    ]);
    GasReading::factory()->create([
        'device_id' => $source->id,
        'lel_pct' => 1.25,
        'h2s_ppm' => 0.5,
        'o2_pct' => 20.8,
        'co_ppm' => 3.0,
        'co2_ppm' => 455.0,
        'recorded_at' => now(),
    ]);

    $this->artisan('ir4:s', [
        'action' => 'm',
        'pole' => '2',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $byRef = [];
    Http::assertSent(function ($request) use (&$byRef): bool {
        if (! str_contains($request->url(), '/api/ingest/gas-readings')) {
            return false;
        }
        $event = $request['events'][0] ?? [];
        $ref = (string) ($event['device_ref'] ?? '');
        $byRef[$ref] = $event;

        return true;
    });

    expect($byRef)->not->toHaveKey('DEV-GAS-02')
        ->and($byRef)->toHaveKeys(['DEV-GAS-01', 'DEV-GAS-03', 'DEV-GAS-04'])
        ->and((float) $byRef['DEV-GAS-01']['co2_ppm'])->toBe(455.0)
        ->and((float) $byRef['DEV-GAS-03']['lel_pct'])->toBe(1.25)
        ->and((float) $byRef['DEV-GAS-04']['o2_pct'])->toBe(20.8);
});

it('limits mimic targets with --to', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $source = Device::factory()->gasDetector()->create([
        'reference' => 'DEV-GAS-02',
    ]);
    GasReading::factory()->create([
        'device_id' => $source->id,
        'co2_ppm' => 420.0,
        'recorded_at' => now(),
    ]);

    $this->artisan('ir4:s', [
        'action' => 'm',
        'pole' => '2',
        '--to' => '1,4',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $refs = [];
    Http::assertSent(function ($request) use (&$refs): bool {
        if (! str_contains($request->url(), '/api/ingest/gas-readings')) {
            return false;
        }
        $refs[] = $request['events'][0]['device_ref'] ?? '';

        return true;
    });

    expect($refs)->toBe(['DEV-GAS-01', 'DEV-GAS-04']);
});

it('requires a source pole for mimic', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'm',
        '--url' => 'http://standby.test',
    ])->assertFailed();

    Http::assertNothingSent();
});
