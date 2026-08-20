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

    /**
     * Statuses hidden from Live View and operator filter selects (DOC-05: retire / maintenance, don't delete).
     *
     * @return list<string>
     */
    public static function nonOperationalValues(): array
    {
        return [
            self::Retired->value,
            self::Maintenance->value,
        ];
    }

    public function isOperational(): bool
    {
        return ! in_array($this->value, self::nonOperationalValues(), true);
    }
}
