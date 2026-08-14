<?php

use App\Support\EdgeDeviceCredentials;
use Illuminate\Support\Facades\Http;

it('posts a one-shot helmet violation without a snapshot field', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'h',
        'a' => '1',
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
        'a' => '2',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $event = $request['events'][0] ?? [];

        return str_contains($request->url(), '/api/ingest/tag-readings')
            && ($event['reader_ref'] ?? null) === 'DEV-RFID-02'
            && ($event['tag_uid'] ?? null) === 'E280116060000203IR4W0001';
    });
});

it('posts arrival rfid at the pole only', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'a',
        'a' => '1',
        '--url' => 'http://standby.test',
    ])->assertSuccessful();

    $readers = [];
    Http::assertSent(function ($request) use (&$readers): bool {
        if (! str_contains($request->url(), '/api/ingest/tag-readings')) {
            return false;
        }
        $readers[] = $request['events'][0]['reader_ref'] ?? '';

        return true;
    });
    expect($readers)->toBe(['DEV-RFID-01']);
});

it('rejects gate rfid', function () {
    Http::fake(['*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('ir4:s', [
        'action' => 'r',
        'a' => 'g',
        '--url' => 'http://standby.test',
    ])->assertFailed();

    Http::assertNothingSent();
});
