<?php

namespace App\Enums;

enum MaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Preventive',
            self::Corrective => 'Corrective',
        };
    }
}
