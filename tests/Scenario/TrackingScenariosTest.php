<?php

use App\Enums\Direction;
use App\Enums\EvacuationStatus;
use App\Enums\MusterStatus;
use App\Enums\ZoneType;
use App\Models\Device;
use App\Models\EntryExitLog;
use App\Models\EvacuationReport;
use App\Models\EvacuationReportEntry;
use App\Models\RfidTag;
use App\Models\TagReading;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Services\ReaderBindingService;
use App\Services\TagService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IngestTestClient;

afterEach(function (): void {
    Carbon::setTestNow();
});

// DOC-21 scenario 1: Read → position → headcount → map (via real ingest).
it('scenario 01: ingest updates position headcount and map without backfill rewind', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'scenario-01';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $gate = Zone::factory()->create(['zone_type' => ZoneType::Gate]);
    $zoneB = Zone::factory()->create(['name' => 'Pad B', 'zone_type' => ZoneType::Work, 'map_x' => 80, 'map_y' => 90]);
    $bindings = app(ReaderBindingService::class);
    $bindings->bind($reader, $gate, now()->subDays(2), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    $tGate = now()->subMinutes(5);
    scenarioIngestTag($reader, $plain, $tag->tag_uid, $tGate);

    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeTrue();

    $bindings->bind($reader, $zoneB, now()->subMinute(), $admin);
    $t2 = now()->subSeconds(20);
    scenarioIngestTag($reader, $plain, $tag->tag_uid, $t2);

    $position = WorkerPosition::query()->where('tag_id', $tag->id)->firstOrFail();
    expect($position->zone_id)->toBe($zoneB->id)
        ->and($position->last_seen_at->timestamp)->toBe($t2->timestamp)
        ->and($position->is_on_site)->toBeTrue();

    Cache::flush();

    $this->actingAs($admin)
        ->getJson(route('tracking.api.headcount'))
        ->assertOk()
        ->assertJsonPath('data.total_on_site', 1);

    $this->getJson(route('tracking.api.positions'))
        ->assertOk()
        ->assertJsonPath('data.positions.0.zone_id', $zoneB->id)
        ->assertJsonPath('data.zones.0.id', $gate->id);

    scenarioIngestTag($reader, $plain, $tag->tag_uid, now()->subHours(2));
    expect($position->fresh()->zone_id)->toBe($zoneB->id)
        ->and(TagReading::query()->where('is_backfill', true)->exists())->toBeTrue();
});

// DOC-21 scenario 2: Gate cycle debounce in/out.
it('scenario 02: gate reads debounce then toggle in and out', function () {
    Carbon::setTestNow('2026-07-18 09:00:00');

    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'scenario-02';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $gate = Zone::factory()->create(['zone_type' => ZoneType::Gate]);
    app(ReaderBindingService::class)->bind($reader, $gate, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->create();
    app(TagService::class)->assign($tag, $worker, $admin);

    $t0 = now();
    scenarioIngestTag($reader, $plain, $tag->tag_uid, $t0);

    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeTrue()
        ->and(EntryExitLog::query()->where('direction', Direction::In)->count())->toBe(1);

    scenarioIngestTag($reader, $plain, $tag->tag_uid, $t0->copy()->addSeconds(30));
    expect(EntryExitLog::query()->count())->toBe(1);

    scenarioIngestTag($reader, $plain, $tag->tag_uid, $t0->copy()->addMinutes(2));
    expect(WorkerPosition::query()->where('tag_id', $tag->id)->value('is_on_site'))->toBeFalse()
        ->and(EntryExitLog::query()->where('direction', Direction::Out)->count())->toBe(1);
});

// DOC-21 scenario 7: Rebind reader across outage — historical zone_id frozen per read.
it('scenario 07: buffered reads resolve to historically correct zones after rebind', function () {
    Carbon::setTestNow('2026-07-18 14:00:00');

    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'scenario-07';
    $reader = Device::factory()->withPlainToken($plain)->create();
    $zoneA = Zone::factory()->create(['name' => 'North pad']);
    $zoneB = Zone::factory()->create(['name' => 'South pad']);
    $bindings = app(ReaderBindingService::class);
    $bindings->bind($reader, $zoneA, now()->subDays(2), $admin);
    $bindings->bind($reader, $zoneB, now()->subMinutes(30), $admin);

    $tag = RfidTag::factory()->create();
    $beforeMove = now()->subHours(3);
    $afterMove = now()->subMinutes(10);

    IngestTestClient::postTagReadings($this, $reader, $plain, [
        IngestTestClient::tagEvent($reader->reference, $tag->tag_uid, recordedAt: $beforeMove->toIso8601String()),
        IngestTestClient::tagEvent($reader->reference, $tag->tag_uid, recordedAt: $afterMove->toIso8601String()),
    ])->assertAccepted();

    $readings = TagReading::query()->orderBy('recorded_at')->get();
    expect($readings)->toHaveCount(2)
        ->and($readings[0]->zone_id)->toBe($zoneA->id)
        ->and($readings[1]->zone_id)->toBe($zoneB->id);
});

// DOC-21 scenario 8: Evacuation lifecycle — trigger, auto-account, manual account, close, PDF.
it('scenario 08: evacuation freezes on-site workers accounts and closes with pdf', function () {
    Carbon::setTestNow('2026-07-18 16:00:00');

    $admin = User::factory()->withRole('Super Admin')->create();
    $gatePlain = 'scenario-08-gate';
    $gateReader = Device::factory()->withPlainToken($gatePlain)->create();
    $gate = Zone::factory()->create(['zone_type' => ZoneType::Gate]);
    $bindings = app(ReaderBindingService::class);
    $bindings->bind($gateReader, $gate, now()->subDay(), $admin);

    $workerA = Worker::factory()->create(['name' => 'Worker Alpha']);
    $workerB = Worker::factory()->create(['name' => 'Worker Beta']);
    $tagA = RfidTag::factory()->create();
    $tagB = RfidTag::factory()->create();
    $tags = app(TagService::class);
    $tags->assign($tagA, $workerA, $admin);
    $tags->assign($tagB, $workerB, $admin);

    scenarioIngestTag($gateReader, $gatePlain, $tagA->tag_uid, now()->subMinutes(5));
    scenarioIngestTag($gateReader, $gatePlain, $tagB->tag_uid, now()->subMinutes(4));

    expect(WorkerPosition::query()->where('is_on_site', true)->count())->toBe(2);

    $this->actingAs($admin)
        ->post(route('tracking.evacuation.store'))
        ->assertRedirect();

    $report = EvacuationReport::query()->firstOrFail();
    expect($report->status)->toBe(EvacuationStatus::Open)
        ->and($report->entries()->count())->toBe(2)
        ->and($report->entries()->where('muster_status', MusterStatus::Unaccounted)->count())->toBe(2);

    scenarioIngestTag($gateReader, $gatePlain, $tagA->tag_uid, now()->subSeconds(30));

    $entryA = EvacuationReportEntry::query()->where('worker_id', $workerA->id)->firstOrFail();
    expect($entryA->fresh()->muster_status)->toBe(MusterStatus::Accounted);

    $entryB = EvacuationReportEntry::query()->where('worker_id', $workerB->id)->firstOrFail();
    $this->post(route('tracking.evacuation.account', [$report, $entryB]))
        ->assertRedirect();
    expect($entryB->fresh()->muster_status)->toBe(MusterStatus::Accounted);

    $this->post(route('tracking.evacuation.close', $report))
        ->assertRedirect();

    expect($report->fresh()->status->value)->toBe('closed')
        ->and($report->fresh()->force_closed)->toBeFalse();

    $this->get(route('tracking.evacuation.download', $report))
        ->assertOk();
});
