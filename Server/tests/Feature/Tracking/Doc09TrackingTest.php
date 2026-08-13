<?php

use App\Enums\AlertType;
use App\Enums\Direction;
use App\Enums\TagStatus;
use App\Enums\ZoneType;
use App\Models\Alert;
use App\Models\Device;
use App\Models\EntryExitLog;
use App\Models\EvacuationReport;
use App\Models\LsrViolation;
use App\Models\RfidTag;
use App\Models\TagReading;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Services\ReaderBindingService;
use App\Services\TagService;
use App\Services\TrackingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function trackingIngest(Device $device, string $plain, string $tagUid, ?DateTimeInterface $at = null): void
{
    test()->postJson(route('api.ingest.tag-readings'), [
        'events' => [[
            'event_uid' => (string) Str::uuid(),
            'reader_ref' => $device->reference,
            'tag_uid' => $tagUid,
            'recorded_at' => Carbon::instance($at ?? now())->toIso8601String(),
        ]],
    ], ['X-Device-Token' => $plain])->assertAccepted();
}

it('assigns tags and rejects double-assign', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();

    $this->actingAs($admin)
        ->post(route('tracking.tags.assign', $tag), ['worker_id' => $worker->id])
        ->assertRedirect();

    expect($tag->fresh()->status)->toBe(TagStatus::Assigned)
        ->and(WorkerPosition::query()->where('tag_id', $tag->id)->exists())->toBeTrue();

    $other = RfidTag::factory()->create();
    $this->actingAs($admin)
        ->post(route('tracking.tags.assign', $other), ['worker_id' => $worker->id])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');
});

it('advances positions on live reads and ignores backfill rewind', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'track-live';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $zoneA = Zone::factory()->create(['name' => 'A', 'zone_type' => ZoneType::Work]);
    $zoneB = Zone::factory()->create(['name' => 'B', 'zone_type' => ZoneType::Work]);
    app(ReaderBindingService::class)->bind($reader, $zoneA, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    $t1 = now()->subMinutes(2);
    trackingIngest($reader, $plain, $tag->tag_uid, $t1);

    // Move binding to zone B for later reads
    app(ReaderBindingService::class)->bind($reader, $zoneB, now()->subMinute(), $admin);
    $t2 = now()->subSeconds(30);
    trackingIngest($reader, $plain, $tag->tag_uid, $t2);

    $position = WorkerPosition::query()->where('tag_id', $tag->id)->first();
    expect($position?->zone_id)->toBe($zoneB->id)
        ->and($position?->last_seen_at->timestamp)->toBe($t2->timestamp);

    // Backfill older read must not rewind
    trackingIngest($reader, $plain, $tag->tag_uid, now()->subMinutes(40));
    expect($position->fresh()->zone_id)->toBe($zoneB->id)
        ->and(TagReading::query()->where('is_backfill', true)->exists())->toBeTrue();
});

it('toggles gate entry/exit with debounce', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'track-gate';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $gate = Zone::factory()->create(['zone_type' => ZoneType::Gate]);
    app(ReaderBindingService::class)->bind($reader, $gate, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    $t0 = now()->subMinutes(5);
    trackingIngest($reader, $plain, $tag->tag_uid, $t0);

    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeTrue()
        ->and(EntryExitLog::query()->where('direction', Direction::In)->count())->toBe(1);

    // Within debounce — ignored for toggle
    trackingIngest($reader, $plain, $tag->tag_uid, $t0->copy()->addSeconds(10));
    expect(EntryExitLog::query()->count())->toBe(1);

    trackingIngest($reader, $plain, $tag->tag_uid, $t0->copy()->addMinutes(2));
    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeFalse()
        ->and(EntryExitLog::query()->where('direction', Direction::Out)->count())->toBe(1);
});

it('raises red zone alert without creating LSR rows', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'track-red';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $red = Zone::factory()->create(['zone_type' => ZoneType::RestrictedRed]);
    app(ReaderBindingService::class)->bind($reader, $red, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);
    WorkerPosition::query()->where('tag_id', $tag->id)->update(['is_on_site' => true]);

    trackingIngest($reader, $plain, $tag->tag_uid, now());

    expect(Alert::query()->where('alert_type', AlertType::RedZoneIntrusion)->count())->toBe(1)
        ->and(LsrViolation::query()->count())->toBe(0);
});

it('sweeps absent on-site tags off site', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create(['present' => true]);
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);
    WorkerPosition::query()->where('tag_id', $tag->id)->update([
        'is_on_site' => true,
        'last_seen_at' => now()->subHours(20),
    ]);

    app(TrackingService::class)->sweepOffsiteTags();

    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeFalse()
        ->and(EntryExitLog::query()->where('source', 'auto_sweep')->exists())->toBeTrue()
        ->and($worker->fresh()->present)->toBeFalse();
});

it('triggers evacuation freezes on-site workers and closes with force', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);
    WorkerPosition::query()->where('tag_id', $tag->id)->update(['is_on_site' => true]);

    $this->actingAs($admin)
        ->post(route('tracking.evacuation.store'))
        ->assertRedirect();

    $report = EvacuationReport::query()->first();
    expect($report)->not->toBeNull()
        ->and($report->entries()->count())->toBe(1);

    $this->actingAs($admin)
        ->getJson(route('tracking.evacuation.snapshot', $report))
        ->assertOk()
        ->assertJsonPath('data.id', $report->id)
        ->assertJsonPath('data.uuid', $report->uuid)
        ->assertJsonPath('data.total', 1);

    $this->actingAs($admin)
        ->from(route('tracking.evacuation.show', $report))
        ->post(route('tracking.evacuation.close', $report))
        ->assertRedirect(route('tracking.evacuation.show', $report))
        ->assertSessionHas(
            'inertia.flash_data.toast.message',
            'Cannot close while workers remain unaccounted; use force close with a note.',
        );

    $this->actingAs($admin)
        ->post(route('tracking.evacuation.close', $report), [
            'force' => true,
            'note' => 'Drill ended with one missing person',
        ])
        ->assertRedirect();

    expect($report->fresh()->status->value)->toBe('closed')
        ->and($report->fresh()->force_closed)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('tracking.evacuation.download', $report))
        ->assertOk();
});

it('auto-registers unknown tags as in_stock without a position', function () {
    $plain = 'track-unknown';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $epc = 'AA0004EF55555555AA21BF43';

    trackingIngest($reader, $plain, $epc);

    $tag = RfidTag::query()->where('tag_uid', $epc)->first();
    expect($tag)->not->toBeNull()
        ->and($tag?->status)->toBe(TagStatus::InStock)
        ->and($tag?->worker_id)->toBeNull()
        ->and(WorkerPosition::query()->where('tag_id', $tag?->id)->exists())->toBeFalse()
        ->and(TagReading::query()->where('tag_id', $tag?->id)->count())->toBe(1);
});

it('renders live tracking with occupancy props on first paint', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get(route('tracking.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tracking/index')
            ->has('headcount')
            ->has('zones')
            ->has('positions')
            ->has('coverage')
            ->has('readings')
            ->where('canSeePositions', true));
});

it('forbids positions for project manager headcount-only role', function () {
    $pm = User::factory()->withRole('Project Manager')->create();

    $this->actingAs($pm)
        ->getJson(route('tracking.api.headcount'))
        ->assertOk();

    $this->actingAs($pm)
        ->getJson(route('tracking.api.positions'))
        ->assertForbidden();
});

it('lists all and per-zone tag readings as records', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'track-readings';
    $readerA = Device::factory()->withPlainToken($plain)->create(['reference' => 'DEV-RFID-A']);
    $readerB = Device::factory()->withPlainToken('track-readings-b')->create(['reference' => 'DEV-RFID-B']);
    $zoneA = Zone::factory()->create(['name' => 'Zone A']);
    $zoneB = Zone::factory()->create(['name' => 'Zone B']);
    app(ReaderBindingService::class)->bind($readerA, $zoneA, now()->subDay(), $admin);
    app(ReaderBindingService::class)->bind($readerB, $zoneB, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    trackingIngest($readerA, $plain, $tag->tag_uid, now()->subMinutes(2));
    trackingIngest($readerB, 'track-readings-b', $tag->tag_uid, now()->subMinute());

    $this->actingAs($admin)
        ->getJson(route('tracking.api.readings'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin)
        ->getJson(route('tracking.api.readings', ['zone_id' => $zoneA->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.zone_name', 'Zone A')
        ->assertJsonPath('data.0.reader_ref', 'DEV-RFID-A');
});

it('lists tag reading records with zone and time filters', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'track-readings-page';
    $reader = Device::factory()->withPlainToken($plain)->create(['reference' => 'DEV-RFID-REC']);
    $zone = Zone::factory()->create(['name' => 'Records Zone']);
    app(ReaderBindingService::class)->bind($reader, $zone, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    trackingIngest($reader, $plain, $tag->tag_uid, now()->subHours(2));
    trackingIngest($reader, $plain, $tag->tag_uid, now()->subMinutes(5));

    $this->actingAs($admin)
        ->get(route('tracking.readings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tracking/readings/index')
            ->has('readings.data', 2));

    $this->actingAs($admin)
        ->get(route('tracking.readings.index', [
            'zone_id' => $zone->id,
            'from' => now()->subMinutes(30)->format('Y-m-d\TH:i'),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tracking/readings/index')
            ->has('readings.data', 1)
            ->where('readings.data.0.zone_name', 'Records Zone')
            ->where('readings.data.0.reader_ref', 'DEV-RFID-REC'));
});

it('manual entry exit correction creates a new row', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();

    $this->actingAs($admin)
        ->post(route('tracking.entry-exit.corrections'), [
            'worker_id' => $worker->id,
            'direction' => 'in',
            'occurred_at' => now()->toIso8601String(),
            'note' => 'Worker entered via unread side path',
        ])
        ->assertRedirect();

    expect(EntryExitLog::query()->where('source', 'manual_correction')->count())->toBe(1)
        ->and($worker->fresh()->present)->toBeTrue();
});
