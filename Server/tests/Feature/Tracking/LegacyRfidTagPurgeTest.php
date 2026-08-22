<?php

use App\Enums\TagStatus;
use App\Models\RfidTag;
use App\Models\TagReading;
use App\Services\LegacyRfidTagPurgeService;
use App\Support\LegacyRfidTagUids;
use App\Support\SiteRfidTags;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('purges legacy dummy tags and moves readings onto site epcs', function () {
    $legacy = RfidTag::query()->create([
        'tag_uid' => 'E280116060000203IR4W0001',
        'status' => TagStatus::InStock,
    ]);

    $replacementEpc = SiteRfidTags::at(1);
    RfidTag::query()->firstOrCreate(
        ['tag_uid' => $replacementEpc],
        ['status' => TagStatus::InStock],
    );

    TagReading::factory()->count(2)->create(['tag_id' => $legacy->id]);

    $summary = app(LegacyRfidTagPurgeService::class)->purge();

    expect($summary)->toHaveCount(1)
        ->and($summary[0]['legacy_uid'])->toBe('E280116060000203IR4W0001')
        ->and($summary[0]['replacement_uid'])->toBe($replacementEpc)
        ->and($summary[0]['readings_moved'])->toBe(2)
        ->and(RfidTag::query()->where('tag_uid', 'E280116060000203IR4W0001')->exists())->toBeFalse()
        ->and(RfidTag::query()->where('tag_uid', $replacementEpc)->first()?->readings()->count())->toBe(2)
        ->and(LegacyRfidTagUids::isLegacy('E280116060000203IR4S0004'))->toBeTrue()
        ->and(LegacyRfidTagUids::isLegacy($replacementEpc))->toBeFalse();
});
