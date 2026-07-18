<?php

namespace App\Enums;

enum PortableDeviceStatus: string
{
    case Approved = 'approved';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Revoked => 'Revoked',
        };
    }
}
