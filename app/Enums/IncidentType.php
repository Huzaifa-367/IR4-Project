<?php

namespace App\Enums;

enum IncidentType: string
{
    case Injury = 'injury';
    case NearMiss = 'near_miss';
    case PropertyDamage = 'property_damage';
    case Environmental = 'environmental';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Injury => 'Injury',
            self::NearMiss => 'Near miss',
            self::PropertyDamage => 'Property damage',
            self::Environmental => 'Environmental',
            self::Other => 'Other',
        };
    }
}
