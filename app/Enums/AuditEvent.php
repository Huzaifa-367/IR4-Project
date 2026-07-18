<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case DataAccess = 'data_access';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case ConfigChanged = 'config_changed';
    case Published = 'published';
    case Acknowledged = 'acknowledged';
    case Exported = 'exported';
    case Wiped = 'wiped';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::LoginFailed => 'Login failed',
            self::DataAccess => 'Data access',
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::ConfigChanged => 'Configuration changed',
            self::Published => 'Published',
            self::Acknowledged => 'Acknowledged',
            self::Exported => 'Exported',
            self::Wiped => 'Wiped',
        };
    }
}
