<?php

namespace App\Enums;

enum IncidentSource: string
{
    case Manual = 'manual';
    case FromAlert = 'from_alert';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::FromAlert => 'From alert',
        };
    }
}
