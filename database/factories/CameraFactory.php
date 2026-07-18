<?php

namespace Database\Factories;

use App\Enums\CameraType;
use App\Enums\HardwareStatus;
use App\Models\Asset;
use App\Models\Camera;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Camera>
 */
class CameraFactory extends Factory
{
    protected $model = Camera::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'name' => 'Camera '.fake()->unique()->numberBetween(1, 999),
            'reference' => 'cam-'.strtolower(Str::random(8)),
            'camera_type' => CameraType::Fixed,
            'processed_by_device_id' => null,
            'stream_url' => 'rtsp://10.0.0.'.fake()->numberBetween(2, 250).'/stream1',
            'ai_enabled' => true,
            'status' => HardwareStatus::Offline,
            'last_frame_at' => null,
            'meta' => null,
        ];
    }
}
