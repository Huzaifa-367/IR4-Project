<?php

namespace App\Enums;

enum GasAlarmLevel: string
{
    case Warning = 'warning';
    case Alarm = 'alarm';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Warning',
            self::Alarm => 'Alarm',
        };
    }
}
