<?php

namespace App\Enums;

enum EntryExitSource: string
{
    case GateReader = 'gate_reader';
    case ManualCorrection = 'manual_correction';
    case AutoSweep = 'auto_sweep';

    public function label(): string
    {
        return match ($this) {
            self::GateReader => 'Gate reader',
            self::ManualCorrection => 'Manual correction',
            self::AutoSweep => 'Auto sweep',
        };
    }
}
