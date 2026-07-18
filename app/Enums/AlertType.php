<?php

namespace App\Enums;

enum AlertType: string
{
    case PpeViolation = 'ppe_violation';
    case GasWarning = 'gas_warning';
    case GasAlarm = 'gas_alarm';
    case RedZoneIntrusion = 'red_zone_intrusion';
    case UnauthorizedZoneAccess = 'unauthorized_zone_access';
    case ZoneOccupancyExceeded = 'zone_occupancy_exceeded';
    case HeightWithoutHarness = 'height_without_harness';
    case FallDetection = 'fall_detection';
    case StationaryTag = 'stationary_tag';
    case WorkerDown = 'worker_down';
    case Evacuation = 'evacuation';
    case DeviceOffline = 'device_offline';
    case CameraOffline = 'camera_offline';
    case EquipmentOverdue = 'equipment_overdue';
    case ClockSkew = 'clock_skew';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::PpeViolation => 'PPE violation',
            self::GasWarning => 'Gas warning',
            self::GasAlarm => 'Gas alarm',
            self::RedZoneIntrusion => 'Red zone intrusion',
            self::UnauthorizedZoneAccess => 'Unauthorized zone access',
            self::ZoneOccupancyExceeded => 'Zone occupancy exceeded',
            self::HeightWithoutHarness => 'Height without harness',
            self::FallDetection => 'Fall detection',
            self::StationaryTag => 'Stationary tag',
            self::WorkerDown => 'Worker down',
            self::Evacuation => 'Evacuation',
            self::DeviceOffline => 'Device offline',
            self::CameraOffline => 'Camera offline',
            self::EquipmentOverdue => 'Equipment overdue',
            self::ClockSkew => 'Clock skew',
            self::System => 'System',
        };
    }
}
