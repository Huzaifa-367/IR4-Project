<?php

namespace App\Support;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;

/**
 * Default severity / audible / suggested_action per alert type (DOC-07 §4 / §8).
 */
final class AlertPolicy
{
    /**
     * @return array{severity: AlertSeverity, audible: bool, suggested_action: ?string}
     */
    public static function defaults(AlertType $type): array
    {
        return match ($type) {
            AlertType::PpeViolation => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => 'log_lsr',
            ],
            AlertType::GasWarning => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => null,
            ],
            AlertType::GasAlarm => [
                'severity' => AlertSeverity::Critical,
                'audible' => true,
                'suggested_action' => null,
            ],
            AlertType::RedZoneIntrusion => [
                'severity' => AlertSeverity::Critical,
                'audible' => true,
                'suggested_action' => 'log_lsr',
            ],
            AlertType::UnauthorizedZoneAccess => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => 'log_lsr',
            ],
            AlertType::ZoneOccupancyExceeded => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => 'log_lsr',
            ],
            AlertType::HeightWithoutHarness => [
                'severity' => AlertSeverity::Critical,
                'audible' => true,
                'suggested_action' => 'log_lsr',
            ],
            AlertType::FallDetection => [
                'severity' => AlertSeverity::Critical,
                'audible' => true,
                'suggested_action' => 'create_incident',
            ],
            AlertType::StationaryTag => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => 'create_incident',
            ],
            AlertType::WorkerDown => [
                'severity' => AlertSeverity::Critical,
                'audible' => true,
                'suggested_action' => 'create_incident',
            ],
            AlertType::Evacuation => [
                'severity' => AlertSeverity::Critical,
                'audible' => true,
                'suggested_action' => null,
            ],
            AlertType::DeviceOffline => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => null,
            ],
            AlertType::CameraOffline => [
                'severity' => AlertSeverity::Warning,
                'audible' => false,
                'suggested_action' => null,
            ],
            AlertType::EquipmentOverdue => [
                'severity' => AlertSeverity::Info,
                'audible' => false,
                'suggested_action' => null,
            ],
            AlertType::ClockSkew => [
                'severity' => AlertSeverity::Info,
                'audible' => false,
                'suggested_action' => null,
            ],
            AlertType::System => [
                'severity' => AlertSeverity::Info,
                'audible' => false,
                'suggested_action' => null,
            ],
        };
    }
}
