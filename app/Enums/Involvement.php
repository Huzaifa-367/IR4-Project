<?php

namespace App\Enums;

enum Involvement: string
{
    case Involved = 'involved';
    case Witness = 'witness';
    case PresentInZone = 'present_in_zone';

    public function label(): string
    {
        return match ($this) {
            self::Involved => 'Involved',
            self::Witness => 'Witness',
            self::PresentInZone => 'Present in zone',
        };
    }
}
