<?php

namespace Database\Seeders;

use App\Support\EdgeDeviceCredentials;
use Illuminate\Database\Seeder;

/**
 * Align existing devices with the default UUID + token table.
 * Safe to re-run. Create the site registry (DemoSeeder) first.
 */
final class DeviceCredentialsSeeder extends Seeder
{
    public function run(): void
    {
        $result = EdgeDeviceCredentials::applyToDevices();

        $this->command?->info(sprintf(
            'Device credentials: %d updated, %d already aligned, %d missing.',
            $result['updated'],
            $result['aligned'],
            $result['missing'],
        ));

        if ($result['missing'] > 0 && $result['updated'] === 0 && $result['aligned'] === 0) {
            $this->command?->warn('No matching devices — run DemoSeeder / ir4:install first.');
        }
    }
}
