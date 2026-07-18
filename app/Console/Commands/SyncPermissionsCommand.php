<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Support\PermissionCatalogue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'ir4:sync-permissions';

    protected $description = 'Ensure the permission catalogue exists and Super Admin holds every permission';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->firstOrFail();

        $superAdmin->syncPermissions(PermissionCatalogue::all());
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $count = Permission::query()
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->count();

        $this->info("Synced {$count} permissions. Super Admin holds all.");

        return self::SUCCESS;
    }
}
