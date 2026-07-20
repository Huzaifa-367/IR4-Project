<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalogue;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionCatalogue::all() as $name) {
            Permission::findOrCreate($name, PermissionCatalogue::GUARD);
        }

        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => PermissionCatalogue::GUARD],
            [
                'is_system' => true,
                'is_read_only' => false,
                'description' => 'Fixed root-of-trust role. Holds every permission.',
            ],
        );

        $superAdmin->forceFill([
            'is_system' => true,
            'is_read_only' => false,
        ])->save();

        $superAdmin->syncPermissions(PermissionCatalogue::all());

        $this->seedStarterRoleIfMissing('Safety Manager', false, $this->safetyManagerPermissions(), 'Full operational authority.');
        $this->seedStarterRoleIfMissing('SCC Operator', false, $this->operatorPermissions(), 'Day-to-day command-centre operation.');
        $this->seedStarterRoleIfMissing('Project Manager', true, $this->projectManagerPermissions(), 'Read-only oversight: KPIs and published reports.');
        $this->seedStarterRoleIfMissing('Client Representative', true, [], 'Configurable read-only client window.');
        $this->seedStarterRoleIfMissing('Field Staff', false, [], 'No platform login — public QR page only.');
        $this->seedStarterRoleIfMissing('Permit Issuer', false, [
            'issue-permit',
            'perform-gas-test',
            'view-permits',
            'approve-permit',
        ], 'Certified permit issuer and gas tester.');
        $this->seedStarterRoleIfMissing('Permit Receiver', false, [
            'request-permit',
            'view-permits',
        ], 'Certified permit receiver — requests and tracks permits.');

        $this->pruneReadOnlyViolations();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function seedStarterRoleIfMissing(string $name, bool $readOnly, array $permissions, string $description): void
    {
        $exists = Role::query()
            ->where('name', $name)
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->exists();

        if ($exists) {
            return;
        }

        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => PermissionCatalogue::GUARD,
            'is_system' => false,
            'is_read_only' => $readOnly,
            'description' => $description,
        ]);

        $role->syncPermissions($permissions);
    }

    private function pruneReadOnlyViolations(): void
    {
        $viewOnly = PermissionCatalogue::viewOnly();

        Role::query()
            ->where('is_read_only', true)
            ->where('is_system', false)
            ->each(function (Role $role) use ($viewOnly): void {
                $current = $role->permissions()->pluck('name')->all();
                $allowed = array_values(array_intersect($current, $viewOnly));

                if ($allowed !== $current) {
                    $role->syncPermissions($allowed);
                }
            });
    }

    /**
     * @return list<string>
     */
    private function safetyManagerPermissions(): array
    {
        return array_values(array_diff(
            PermissionCatalogue::all(),
            ['manage-roles'],
        ));
    }

    /**
     * @return list<string>
     */
    private function operatorPermissions(): array
    {
        return [
            'view-dashboard',
            'view-live-cameras',
            'view-ppe',
            'review-ppe',
            'view-tracking',
            'view-worker-identity',
            'manage-workers',
            'manage-tags',
            'manage-zones',
            'view-entry-exit',
            'manage-portable-devices',
            'trigger-evacuation',
            'manage-evacuation',
            'view-gas',
            'acknowledge-alerts',
            'configure-alerts',
            'view-equipment',
            'manage-equipment',
            'view-incidents',
            'log-incidents',
            'view-lsr',
            'log-lsr',
            'close-lsr',
            'view-permits',
            'request-permit',
            'perform-gas-test',
            'manage-worker-documents',
            'view-reports',
            'log-vehicle-violations',
        ];
    }

    /**
     * @return list<string>
     */
    private function projectManagerPermissions(): array
    {
        return [
            'view-dashboard',
            'view-tracking',
            'view-equipment',
            'view-reports',
        ];
    }
}
