<?php

namespace App\Enums;

enum WorkerType: string
{
    case Employee = 'employee';
    case Contractor = 'contractor';
    case Visitor = 'visitor';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Contractor => 'Contractor',
            self::Visitor => 'Visitor',
        };
    }
}
