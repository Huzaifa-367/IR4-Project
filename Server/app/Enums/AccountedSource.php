<?php

namespace App\Enums;

enum AccountedSource: string
{
    case MusterReader = 'muster_reader';
    case GateExit = 'gate_exit';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::MusterReader => 'Muster reader',
            self::GateExit => 'Gate exit',
            self::Manual => 'Manual',
        };
    }
}
