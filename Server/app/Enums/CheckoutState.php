<?php

namespace App\Enums;

enum CheckoutState: string
{
    case Available = 'available';
    case CheckedOut = 'checked_out';
    case OverdueReturn = 'overdue_return';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::CheckedOut => 'Checked out',
            self::OverdueReturn => 'Overdue return',
        };
    }
}
