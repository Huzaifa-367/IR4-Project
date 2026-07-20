<?php

namespace App\Enums;

enum GasTestPhase: string
{
    case PreStart = 'pre_start';
    case Periodic = 'periodic';
    case PostBreak = 'post_break';
    case Renewal = 'renewal';

    public function label(): string
    {
        return match ($this) {
            self::PreStart => 'Pre-start',
            self::Periodic => 'Periodic',
            self::PostBreak => 'Post-break',
            self::Renewal => 'Renewal',
        };
    }
}
