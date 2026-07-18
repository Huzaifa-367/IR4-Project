<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case InService = 'in_service';
    case OutOfService = 'out_of_service';
    case UnderMaintenance = 'under_maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::InService => 'In service',
            self::OutOfService => 'Out of service',
            self::UnderMaintenance => 'Under maintenance',
            self::Retired => 'Retired',
        };
    }
}
