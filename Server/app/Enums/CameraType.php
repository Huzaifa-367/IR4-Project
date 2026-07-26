<?php

namespace App\Enums;

enum CameraType: string
{
    case Fixed = 'fixed';
    case Ptz = 'ptz';
    case Dome = 'dome';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Ptz => 'PTZ',
            self::Dome => 'Dome',
            self::Other => 'Other',
        };
    }
}
