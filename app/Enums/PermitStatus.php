<?php

namespace App\Enums;

enum PermitStatus: string
{
    case Draft = 'draft';
    case PendingInspection = 'pending_inspection';
    case PendingGasTest = 'pending_gas_test';
    case PendingIssue = 'pending_issue';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingInspection => 'Pending inspection',
            self::PendingGasTest => 'Pending gas test',
            self::PendingIssue => 'Pending issue',
            self::PendingApproval => 'Pending approval',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Rejected',
        };
    }
}
