<?php

namespace App\Enums;

enum CameraRoiSetStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Stale = 'stale';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Stale => 'Stale',
        };
    }
}
