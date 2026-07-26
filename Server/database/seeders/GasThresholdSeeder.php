<?php

namespace Database\Seeders;

use App\Enums\GasType;
use App\Enums\ThresholdDirection;
use App\Models\GasThreshold;
use Illuminate\Database\Seeder;

final class GasThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['gas_type' => GasType::Lel, 'warning_level' => 10, 'alarm_level' => 20, 'unit' => '%LEL', 'direction' => ThresholdDirection::Above],
            ['gas_type' => GasType::H2s, 'warning_level' => 5, 'alarm_level' => 10, 'unit' => 'ppm', 'direction' => ThresholdDirection::Above],
            ['gas_type' => GasType::O2Low, 'warning_level' => 19.5, 'alarm_level' => 19.0, 'unit' => '%vol', 'direction' => ThresholdDirection::Below],
            ['gas_type' => GasType::O2High, 'warning_level' => 23.0, 'alarm_level' => 23.5, 'unit' => '%vol', 'direction' => ThresholdDirection::Above],
            ['gas_type' => GasType::Co, 'warning_level' => 25, 'alarm_level' => 50, 'unit' => 'ppm', 'direction' => ThresholdDirection::Above],
            ['gas_type' => GasType::Co2, 'warning_level' => 5000, 'alarm_level' => 30000, 'unit' => 'ppm', 'direction' => ThresholdDirection::Above],
        ];

        foreach ($defaults as $row) {
            GasThreshold::query()->firstOrCreate(
                ['gas_type' => $row['gas_type']],
                [
                    'warning_level' => $row['warning_level'],
                    'alarm_level' => $row['alarm_level'],
                    'unit' => $row['unit'],
                    'direction' => $row['direction'],
                    'is_active' => true,
                ],
            );
        }
    }
}
