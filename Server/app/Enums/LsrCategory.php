<?php

namespace App\Enums;

enum LsrCategory: string
{
    case MissingPpe = 'missing_ppe';
    case RedZoneIntrusion = 'red_zone_intrusion';
    case UnauthorizedZoneAccess = 'unauthorized_zone_access';
    case HeightWithoutHarness = 'height_without_harness';
    case WorkerDown = 'worker_down';
    case ZoneOccupancyExceeded = 'zone_occupancy_exceeded';
    case WorkingWithoutPermit = 'working_without_permit';
    case HotWorkWithoutFireWatch = 'hot_work_without_fire_watch';
    case SimopsViolation = 'simops_violation';

    public function label(): string
    {
        return match ($this) {
            self::MissingPpe => 'Missing PPE',
            self::RedZoneIntrusion => 'Red zone intrusion',
            self::UnauthorizedZoneAccess => 'Unauthorized zone access',
            self::HeightWithoutHarness => 'Height without harness',
            self::WorkerDown => 'Worker down',
            self::ZoneOccupancyExceeded => 'Zone occupancy exceeded',
            self::WorkingWithoutPermit => 'Working without permit',
            self::HotWorkWithoutFireWatch => 'Hot work without fire watch',
            self::SimopsViolation => 'SIMOPS violation',
        };
    }
}
