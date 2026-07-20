<?php

namespace App\Enums;

enum GasTestResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Fail => 'Fail',
        };
    }
}
