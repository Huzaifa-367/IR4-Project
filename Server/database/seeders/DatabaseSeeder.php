<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            RolePermissionSeeder::class,
            PermitCatalogueSeeder::class,
            GasThresholdSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }

        // Existing installs (incl. production): bind unbound cameras to sibling RFID zones.
        $this->call(CameraZoneBindingSeeder::class);

        $this->call(DeviceCredentialsSeeder::class);
    }
}
