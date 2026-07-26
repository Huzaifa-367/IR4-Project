<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Maintenance => 'Maintenance',
            self::Offline => 'Offline',
        };
    }
}
