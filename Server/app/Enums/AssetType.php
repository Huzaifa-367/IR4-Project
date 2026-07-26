<?php

namespace App\Enums;

enum AssetType: string
{
    case Pole = 'pole';
    case Gate = 'gate';
    case SccServer = 'scc_server';
    case SccWorkstation = 'scc_workstation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pole => 'Pole',
            self::Gate => 'Gate',
            self::SccServer => 'SCC Server',
            self::SccWorkstation => 'SCC Workstation',
            self::Other => 'Other',
        };
    }
}
