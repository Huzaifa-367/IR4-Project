<?php

namespace App\Enums;

enum ScheduleType: string
{
    case Inspection = 'inspection';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Inspection => 'Inspection',
            self::Service => 'Service',
        };
    }
}
