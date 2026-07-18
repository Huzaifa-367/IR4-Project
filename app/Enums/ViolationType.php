<?php

namespace App\Enums;

enum ViolationType: string
{
    case MissingHelmet = 'missing_helmet';
    case MissingVest = 'missing_vest';
    case MissingHarness = 'missing_harness';
    case MissingMask = 'missing_mask';

    public function label(): string
    {
        return match ($this) {
            self::MissingHelmet => 'Missing helmet',
            self::MissingVest => 'Missing vest',
            self::MissingHarness => 'Missing harness',
            self::MissingMask => 'Missing mask',
        };
    }
}
