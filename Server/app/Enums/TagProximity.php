<?php

namespace App\Enums;

enum TagProximity: string
{
    case Near = 'near';
    case Mid = 'mid';
    case Far = 'far';

    public function label(): string
    {
        return match ($this) {
            self::Near => 'Near',
            self::Mid => 'Mid',
            self::Far => 'Far',
        };
    }
}
