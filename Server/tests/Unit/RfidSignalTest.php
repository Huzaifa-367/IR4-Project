<?php

use App\Enums\TagProximity;
use App\Support\RfidSignal;

it('classifies rssi into proximity bands', function () {
    expect(RfidSignal::proximity(null))->toBeNull()
        ->and(RfidSignal::proximity(-26))->toBe(TagProximity::Near)
        ->and(RfidSignal::proximity(-40))->toBe(TagProximity::Near)
        ->and(RfidSignal::proximity(-55))->toBe(TagProximity::Mid)
        ->and(RfidSignal::proximity(-60))->toBe(TagProximity::Mid)
        ->and(RfidSignal::proximity(-72))->toBe(TagProximity::Far);
});
