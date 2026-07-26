<?php

namespace App\Enums;

enum GasType: string
{
    case Lel = 'lel';
    case H2s = 'h2s';
    case O2Low = 'o2_low';
    case O2High = 'o2_high';
    case Co = 'co';
    case Co2 = 'co2';

    public function label(): string
    {
        return match ($this) {
            self::Lel => 'LEL',
            self::H2s => 'H₂S',
            self::O2Low => 'O₂ low',
            self::O2High => 'O₂ high',
            self::Co => 'CO',
            self::Co2 => 'CO₂',
        };
    }

    public function readingColumn(): string
    {
        return match ($this) {
            self::Lel => 'lel_pct',
            self::H2s => 'h2s_ppm',
            self::O2Low, self::O2High => 'o2_pct',
            self::Co => 'co_ppm',
            self::Co2 => 'co2_ppm',
        };
    }
}
