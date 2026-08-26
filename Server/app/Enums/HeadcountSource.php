<?php

namespace App\Enums;

enum HeadcountSource: string
{
    case Rfid = 'rfid';
    case Camera = 'camera';

    public function label(): string
    {
        return match ($this) {
            self::Rfid => 'RFID',
            self::Camera => 'Camera AI',
        };
    }
}
