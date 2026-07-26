<?php

namespace App\Enums;

enum TagStatus: string
{
    case InStock = 'in_stock';
    case Assigned = 'assigned';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::Assigned => 'Assigned',
            self::Lost => 'Lost',
            self::Damaged => 'Damaged',
            self::Retired => 'Retired',
        };
    }
}
