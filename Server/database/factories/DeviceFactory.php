<?php

namespace Database\Factories;

use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Asset;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = 'dev_'.Str::random(40);

        return [
            'asset_id' => Asset::factory(),
            'name' => fake()->company().' Reader',
            'reference' => 'DEV-'.strtoupper(Str::random(8)),
            'serial_number' => null,
            'device_type' => DeviceType::RfidReader,
            'status' => HardwareStatus::Online,
            'api_token_hash' => hash('sha256', $plain),
            'token_issued_at' => now(),
            'config' => null,
            'last_seen_at' => null,
        ];
    }

    public function withPlainToken(string $plain): static
    {
        return $this->state(fn (): array => [
            'api_token_hash' => hash('sha256', $plain),
            'token_issued_at' => now(),
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => [
            'status' => HardwareStatus::Retired,
        ]);
    }

    public function withoutToken(): static
    {
        return $this->state(fn (): array => [
            'api_token_hash' => null,
            'token_issued_at' => null,
        ]);
    }

    public function gasDetector(): static
    {
        return $this->state(fn (): array => [
            'device_type' => DeviceType::GasDetector,
            'name' => fake()->company().' Gas Detector',
        ]);
    }

    public function camera(): static
    {
        return $this->state(fn (): array => [
            'device_type' => DeviceType::Camera,
            'name' => 'Camera '.fake()->unique()->numberBetween(1, 999),
            'reference' => 'cam-'.strtolower(Str::random(8)),
            'camera_type' => CameraType::Fixed,
            'stream_url' => 'rtsp://10.0.0.'.fake()->numberBetween(2, 250).'/stream1',
            'api_url' => 'http://127.0.0.1:8600/rois',
            'ai_enabled' => true,
            'status' => HardwareStatus::Offline,
            'last_frame_at' => null,
            'meta' => null,
            'serial_number' => null,
        ]);
    }
}
