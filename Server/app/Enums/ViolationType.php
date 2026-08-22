<?php

namespace App\Enums;

enum ViolationType: string
{
    case MissingHelmet = 'missing_helmet';
    case MissingVest = 'missing_vest';
    case MissingHarness = 'missing_harness';
    case MissingMask = 'missing_mask';
    case Fall = 'fall';

    public function label(): string
    {
        return match ($this) {
            self::MissingHelmet => 'Missing helmet',
            self::MissingVest => 'Missing vest',
            self::MissingHarness => 'Working at heights',
            self::MissingMask => 'Missing mask',
            self::Fall => 'Fall detection',
        };
    }
}
