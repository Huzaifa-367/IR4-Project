<?php

namespace App\Support;

/**
 * Canonical permission catalogue (DOC-03 §3). Single source of truth for seeding + TS export.
 *
 * Groups are module-based (one DOC / domain surface per group). Route middleware
 * declares the specific permission on each route — never a whole-module gate.
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
            'Dashboard' => [
                'view-dashboard',
            ],
            'Live view & cameras' => [
                'view-live-cameras',
            ],
            'Alerts' => [
                'acknowledge-alerts',
                'resolve-alerts',
            ],
            'PPE' => [
                'view-ppe',
                'update-ppe-violations',
                'export-ppe-violations',
            ],
            'Tracking / RFID' => [
                'view-tracking',
                'view-worker-identity',
                'create-workers',
                'update-workers',
                'delete-workers',
                'create-tags',
                'update-tags',
                'view-zones',
                'create-zones',
                'update-zones',
                'delete-zones',
                'view-entry-exit',
                'view-portable-devices',
                'create-portable-devices',
                'update-portable-devices',
                'create-evacuation',
                'update-evacuation',
            ],
            'Gas & CO₂' => [
                'view-gas',
                'view-gas-thresholds',
                'update-gas-thresholds',
            ],
            'Equipment / QR' => [
                'view-equipment',
                'create-equipment',
                'update-equipment',
                'delete-equipment',
            ],
            'HSE incidents & LSR' => [
                'view-incidents',
                'create-incidents',
                'update-incidents',
                'view-lsr',
                'create-lsr',
                'update-lsr',
            ],
            'Permit to Work' => [
                'view-permits',
                'create-permits',
                'update-permits',
                'create-permit-gas-tests',
                'view-permit-catalogue',
                'create-permit-catalogue',
                'update-permit-catalogue',
                'delete-permit-catalogue',
                'view-worker-documents',
                'create-worker-documents',
                'update-worker-documents',
                'delete-worker-documents',
            ],
            'Reports' => [
                'view-reports',
                'create-reports',
                'update-reports',
                'view-vehicle-violations',
                'create-vehicle-violations',
                'delete-vehicle-violations',
            ],
            'Administration' => [
                'view-audit-log',
                'view-users',
                'create-users',
                'update-users',
                'view-roles',
                'create-roles',
                'update-roles',
                'delete-roles',
                'view-devices',
                'create-devices',
                'update-devices',
                'delete-devices',
                'view-settings',
                'update-settings',
                'update-alert-settings',
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
