<?php

namespace App\Enums;

enum PermitApprovalAction: string
{
    case Issued = 'issued';
    case Approved = 'approved';
    case Renewed = 'renewed';
    case Suspended = 'suspended';
    case Resumed = 'resumed';
    case Cancelled = 'cancelled';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Approved => 'Approved',
            self::Renewed => 'Renewed',
            self::Suspended => 'Suspended',
            self::Resumed => 'Resumed',
            self::Cancelled => 'Cancelled',
            self::Closed => 'Closed',
            self::Rejected => 'Rejected',
        };
    }
}
