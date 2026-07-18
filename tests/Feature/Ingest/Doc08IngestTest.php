<?php

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\Camera;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\GasReading;
use App\Models\PpeViolation;
use App\Models\RfidTag;
use App\Models\TagReading;
use App\Services\SettingsService;
use App\Support\Ingest\LiveState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

function ingestHeaders(string $plain): array
{
    return ['X-Device-Token' => $plain];
}

function tagEvent(string $readerRef, string $tagUid, ?string $uid = null, ?string $recordedAt = null): array
{
    return [
        'event_uid' => $uid ?? (string) Str::uuid(),
        'reader_ref' => $readerRef,
        'tag_uid' => $tagUid,
        'recorded_at' => $recordedAt ?? now()->toIso8601String(),
    ];
}

it('accepts a tag-readings batch and updates device liveness', function () {
    $plain = 'ingest-token-ok';
    $device = Device::factory()->withPlainToken($plain)->create([
        'last_seen_at' => now()->subHour(),
    ]);
    $tag = RfidTag::factory()->create();
    $assetId = $device->asset_id;

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [tagEvent($device->reference, $tag->tag_uid)],
    ], ingestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1)
        ->assertJsonPath('duplicates', 0)
        ->assertJsonPath('rejected', []);

    expect(TagReading::query()->count())->toBe(1)
        ->and($device->fresh()->last_seen_at)->not->toBeNull()
        ->and(Asset::query()->find($assetId)?->last_heartbeat_at)->not->toBeNull();
});

it('reports duplicates on idempotent resend', function () {
    $plain = 'ingest-dup';
    $device = Device::factory()->withPlainToken($plain)->create();
    $tag = RfidTag::factory()->create();
    $uid = (string) Str::uuid();
    $payload = ['events' => [tagEvent($device->reference, $tag->tag_uid, $uid)]];

    $this->postJson(route('api.ingest.tag-readings'), $payload, ingestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $this->postJson(route('api.ingest.tag-readings'), $payload, ingestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('duplicates', 1);

    expect(TagReading::query()->count())->toBe(1);
});

it('partially accepts a mixed batch with unknown reference', function () {
    $plain = 'ingest-partial';
    $device = Device::factory()->withPlainToken($plain)->create();
    $tag = RfidTag::factory()->create();

    $response = $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [
            tagEvent($device->reference, $tag->tag_uid),
            tagEvent('missing-reader-ref', $tag->tag_uid),
        ],
    ], ingestHeaders($plain));

    $response->assertStatus(202);

    expect($response->json('accepted'))->toBe(1)
        ->and($response->json('rejected'))->toHaveCount(1)
        ->and($response->json('rejected.0.code'))->toBe('UNKNOWN_REFERENCE')
        ->and(TagReading::query()->count())->toBe(1);
});

it('rejects batches larger than ingest.max_batch', function () {
    $plain = 'ingest-oversize';
    $device = Device::factory()->withPlainToken($plain)->create();
    $tag = RfidTag::factory()->create();
    app(SettingsService::class)->set('ingest.max_batch', 2);

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [
            tagEvent($device->reference, $tag->tag_uid),
            tagEvent($device->reference, $tag->tag_uid),
            tagEvent($device->reference, $tag->tag_uid),
        ],
    ], ingestHeaders($plain))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('marks backfill events when recorded_at is older than threshold', function () {
    $plain = 'ingest-backfill';
    $device = Device::factory()->withPlainToken($plain)->create();
    $tag = RfidTag::factory()->create();
    $old = now()->subMinutes(30)->toIso8601String();

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [tagEvent($device->reference, $tag->tag_uid, recordedAt: $old)],
    ], ingestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    expect(TagReading::query()->first()?->is_backfill)->toBeTrue();
});

it('clamps future recorded_at and raises one clock_skew alert per day', function () {
    $plain = 'ingest-skew';
    $device = Device::factory()->withPlainToken($plain)->create();
    $tag = RfidTag::factory()->create();
    $future = now()->addHours(2)->toIso8601String();

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [
            tagEvent($device->reference, $tag->tag_uid, recordedAt: $future),
            tagEvent($device->reference, $tag->tag_uid, recordedAt: $future),
        ],
    ], ingestHeaders($plain))
        ->assertAccepted()
        ->assertJsonPath('accepted', 2);

    $rows = TagReading::query()->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn (TagReading $e) => $e->clock_skew))->toBeTrue()
        ->and(Alert::query()->where('alert_type', AlertType::ClockSkew)->count())->toBe(1);
});

it('rate limits ingest per device', function () {
    $plain = 'ingest-rate';
    $device = Device::factory()->withPlainToken($plain)->create();
    $tag = RfidTag::factory()->create();
    app(SettingsService::class)->set('ingest.rate_per_minute', 2);
    RateLimiter::clear('ingest:'.$device->id);

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [tagEvent($device->reference, $tag->tag_uid)],
    ], ingestHeaders($plain))->assertAccepted();
    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [tagEvent($device->reference, $tag->tag_uid)],
    ], ingestHeaders($plain))->assertAccepted();

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [tagEvent($device->reference, $tag->tag_uid)],
    ], ingestHeaders($plain))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'RATE_LIMITED');
});

it('routes ppe gas and environmental streams to domain tables', function () {
    $plain = 'ingest-multi';
    $device = Device::factory()->withPlainToken($plain)->create();
    $camera = Camera::factory()->create(['reference' => 'cam-north']);

    $this->postJson(route('api.ingest.ppe-violations'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'camera_ref' => $camera->reference,
            'event_type' => 'missing_helmet',
            'detected_at' => now()->toIso8601String(),
            'confidence' => 0.91,
        ]],
    ], ingestHeaders($plain))->assertAccepted()->assertJsonPath('accepted', 1);

    expect(PpeViolation::query()->count())->toBe(1);

    $this->postJson(route('api.ingest.gas-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'h2s_ppm' => 2.5,
        ]],
    ], ingestHeaders($plain))->assertAccepted()->assertJsonPath('accepted', 1);

    expect(GasReading::query()->count())->toBe(1);

    $this->postJson(route('api.ingest.environmental-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'temperature_c' => 34.1,
        ]],
    ], ingestHeaders($plain))->assertAccepted()->assertJsonPath('accepted', 1);

    expect(EnvironmentalReading::query()->count())->toBe(1);
});

it('only advances live state forward', function () {
    $current = Carbon::parse('2026-07-18 10:00:00');
    $older = Carbon::parse('2026-07-18 09:59:00');
    $newer = Carbon::parse('2026-07-18 10:01:00');

    expect(LiveState::shouldAdvance($current, $older))->toBeFalse()
        ->and(LiveState::shouldAdvance($current, $newer))->toBeTrue()
        ->and(LiveState::shouldAdvance(null, $older))->toBeTrue();
});
