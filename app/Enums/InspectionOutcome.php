<?php

namespace App\Enums;

enum InspectionOutcome: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case PassWithNotes = 'pass_with_notes';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Fail => 'Fail',
            self::PassWithNotes => 'Pass with notes',
        };
    }
}
