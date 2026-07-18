<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Ok = 'ok';
    case Damaged = 'damaged';
    case NeedsService = 'needs_service';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Damaged => 'Damaged',
            self::NeedsService => 'Needs service',
        };
    }
}
