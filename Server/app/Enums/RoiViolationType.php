<?php

namespace App\Enums;

enum RoiViolationType: string
{
    case Intrusion = 'roi_intrusion';

    public function label(): string
    {
        return match ($this) {
            self::Intrusion => 'Red Zone intrusion',
        };
    }
}
