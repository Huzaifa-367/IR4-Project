<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Classified = 'classified';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under review',
            self::Classified => 'Classified',
            self::Closed => 'Closed',
        };
    }
}
