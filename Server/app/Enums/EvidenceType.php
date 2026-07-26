<?php

namespace App\Enums;

enum EvidenceType: string
{
    case Snapshot = 'snapshot';
    case RfidZoneSnapshot = 'rfid_zone_snapshot';
    case PpeViolation = 'ppe_violation';
    case Document = 'document';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Snapshot => 'Snapshot',
            self::RfidZoneSnapshot => 'RFID zone snapshot',
            self::PpeViolation => 'PPE violation',
            self::Document => 'Document',
            self::Note => 'Note',
        };
    }
}
