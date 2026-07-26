<?php

namespace App\Enums;

enum HardwareStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Degraded = 'degraded';
    case Fault = 'fault';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Degraded => 'Degraded',
            self::Fault => 'Fault',
            self::Maintenance => 'Maintenance',
            self::Retired => 'Retired',
        };
    }
}
