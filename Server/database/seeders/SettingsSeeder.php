<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('ir4.settings', []);

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
