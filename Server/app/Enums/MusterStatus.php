<?php

namespace App\Enums;

enum MusterStatus: string
{
    case Unaccounted = 'unaccounted';
    case Accounted = 'accounted';

    public function label(): string
    {
        return match ($this) {
            self::Unaccounted => 'Unaccounted',
            self::Accounted => 'Accounted',
        };
    }
}
