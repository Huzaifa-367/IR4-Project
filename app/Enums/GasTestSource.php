<?php

namespace App\Enums;

enum GasTestSource: string
{
    case Manual = 'manual';
    case Device = 'device';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Device => 'Device',
        };
    }
}
