<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Support\PermissionCatalogue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keep the permission catalogue and Super Admin grant set in sync (DOC-03 §7.2).
 *
 * Re-runs RolePermissionSeeder, then explicitly syncs every catalogue permission
 * onto the locked Super Admin role and clears Spatie's permission cache.
 * Other roles are not auto-granted new permissions — admins assign those in UI.
 *
 * Usage:
 *   php artisan ir4:sync-permissions
 */
final class SyncPermissionsCommand extends Command
{
    protected $signature = 'ir4:sync-permissions';

    protected $description = 'Ensure the permission catalogue exists and Super Admin holds every permission';

    public function handle(): int
    {
        // Upsert permission rows + starter roles from PermissionCatalogue.
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->firstOrFail();

        // Super Admin always holds the full set — no Gate::before bypass.
        $superAdmin->syncPermissions(PermissionCatalogue::all());
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $count = Permission::query()
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->count();

        $this->info("Synced {$count} permissions. Super Admin holds all.");

        return self::SUCCESS;
    }
}
