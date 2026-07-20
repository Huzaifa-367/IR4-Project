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
    $zoneA = Zone::factory()->create(['name' => 'A', 'zone_type' => ZoneType::Work, 'map_x' => 20, 'map_y' => 20]);
    $zoneB = Zone::factory()->create(['name' => 'B', 'zone_type' => ZoneType::Work, 'map_x' => 80, 'map_y' => 80]);
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

it('forbids positions for project manager headcount-only role', function () {
    $pm = User::factory()->withRole('Project Manager')->create();

    $this->actingAs($pm)
        ->getJson(route('tracking.api.headcount'))
        ->assertOk();

    $this->actingAs($pm)
        ->getJson(route('tracking.api.positions'))
        ->assertForbidden();
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
