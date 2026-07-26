<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Unreviewed = 'unreviewed';
    case Confirmed = 'confirmed';
    case FalsePositive = 'false_positive';

    public function label(): string
    {
        return match ($this) {
            self::Unreviewed => 'Unreviewed',
            self::Confirmed => 'Confirmed',
            self::FalsePositive => 'False positive',
        };
    }
}
