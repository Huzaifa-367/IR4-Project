<?php

namespace App\Support;

/**
 * Canonical permission catalogue (DOC-03 §3). Single source of truth for seeding + TS export.
 */
final class PermissionCatalogue
{
    public const GUARD = 'web';

    /**
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'Live view & cameras' => [
                'view-live-cameras',
            ],
            'PPE' => [
                'view-ppe',
                'review-ppe',
                'export-ppe-reports',
            ],
            'Tracking / RFID' => [
                'view-tracking',
                'view-worker-identity',
                'manage-workers',
                'manage-tags',
                'manage-zones',
                'view-entry-exit',
                'manage-portable-devices',
                'trigger-evacuation',
                'manage-evacuation',
            ],
            'Gas & CO₂' => [
                'view-gas',
                'manage-gas-thresholds',
            ],
            'Alerts' => [
                'acknowledge-alerts',
                'configure-alerts',
            ],
            'Equipment / QR' => [
                'view-equipment',
                'manage-equipment',
            ],
            'HSE incidents & LSR' => [
                'view-incidents',
                'log-incidents',
                'classify-incidents',
                'view-lsr',
                'log-lsr',
                'close-lsr',
            ],
            'Reports' => [
                'view-reports',
                'generate-reports',
                'publish-reports',
                'log-vehicle-violations',
            ],
            'Dashboard' => [
                'view-dashboard',
            ],
            'Administration' => [
                'view-audit-log',
                'manage-users',
                'manage-roles',
                'manage-devices',
                'manage-settings',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::grouped() as $permissions) {
            foreach ($permissions as $permission) {
                $names[] = $permission;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function viewOnly(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $name): bool => str_starts_with($name, 'view-'),
        ));
    }
}
