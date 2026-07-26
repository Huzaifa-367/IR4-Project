<?php

namespace App\Enums;

enum ZoneType: string
{
    case Work = 'work';
    case Gate = 'gate';
    case RestrictedRed = 'restricted_red';
    case HeightWork = 'height_work';
    case MusterPoint = 'muster_point';
    case Laydown = 'laydown';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Work => 'Work',
            self::Gate => 'Gate',
            self::RestrictedRed => 'Restricted (red)',
            self::HeightWork => 'Height work',
            self::MusterPoint => 'Muster point',
            self::Laydown => 'Laydown',
            self::Other => 'Other',
        };
    }
}
