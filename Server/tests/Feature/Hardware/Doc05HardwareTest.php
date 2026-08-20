<?php

use App\Enums\HardwareStatus;
use App\Events\DeviceStatusChanged;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\Device;
use App\Models\User;
use App\Services\AssetHealthService;
use App\Services\HardwareRegistryService;
use App\Support\EdgeDeviceCredentials;
use Database\Seeders\DeviceCredentialsSeeder;
use Illuminate\Support\Facades\Event;

it('registers assets devices and cameras', function () {
    $admin = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($admin)
        ->post(route('settings.assets.store'), [
            'asset_type' => 'pole',
            'name' => 'Pole 1',
            'identifier' => 'POLE-1',
            'is_mobile' => true,
        ])
        ->assertRedirect();

    $asset = Asset::query()->where('identifier', 'POLE-1')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('settings.devices.store'), [
            'asset_id' => $asset->id,
            'name' => 'Pole 1 Reader',
            'reference' => 'pole1-reader',
            'device_type' => 'rfid_reader',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('settings.cameras.store'), [
            'asset_id' => $asset->id,
            'name' => 'Pole 1 North',
            'reference' => 'pole1-cam-n',
            'camera_type' => 'fixed',
            'stream_url' => 'rtsp://10.0.0.5/stream1',
        ])
        ->assertRedirect();

    expect(Device::query()->count())->toBe(1)
        ->and(Camera::query()->count())->toBe(1);
});

it('issues a plaintext token once and stores only the hash', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->withoutToken()->create();

    $this->actingAs($admin)
        ->post(route('settings.devices.token', $device))
        ->assertRedirect(route('settings.devices.index'))
        ->assertSessionHas('plain_device_token');

    $payload = session('plain_device_token');
    $plain = $payload['token'];

    expect($device->fresh()->api_token_hash)->toBe(hash('sha256', $plain))
        ->and($device->fresh()->api_token_hash)->not->toBe($plain)
        ->and(AuditLog::query()->where('payload->target', 'device_token')->exists())->toBeTrue();

    $this->get(route('settings.devices.index'))
        ->assertOk();
});

it('authenticates with issued token and updates asset heartbeat', function () {
    $device = Device::factory()->withoutToken()->create();
    $result = app(HardwareRegistryService::class)->issueToken($device);
    $plain = $result['plain_token'];

    $this->postJson(route('api.devices.heartbeat', $device), [
        'status' => 'degraded',
        'meta' => ['firmware' => '1.0.0'],
    ], [
        'X-Device-Token' => $plain,
    ])->assertOk()
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.device_uuid', $device->uuid);

    expect($device->fresh()->last_seen_at)->not->toBeNull()
        ->and($device->fresh()->status)->toBe(HardwareStatus::Degraded)
        ->and($device->fresh()->config)->toMatchArray(['firmware' => '1.0.0'])
        ->and($device->fresh()->asset?->fresh()->last_heartbeat_at)->not->toBeNull();
});

it('blocks deleting an asset that still has children', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $asset = Asset::factory()->create();
    Device::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($admin)
        ->delete(route('settings.assets.destroy', $asset))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');
});

it('marks stale devices offline via health service', function () {
    $device = Device::factory()->create([
        'status' => HardwareStatus::Online,
        'last_seen_at' => now()->subMinutes(20),
    ]);

    app(AssetHealthService::class)->markStale();

    expect($device->fresh()->status)->toBe(HardwareStatus::Offline);
});

it('broadcasts camera offline on markStale with asset_id', function () {
    Event::fake([DeviceStatusChanged::class]);

    $camera = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'last_frame_at' => now()->subMinutes(20),
    ]);

    app(AssetHealthService::class)->markStale();

    expect($camera->fresh()->status)->toBe(HardwareStatus::Offline);
    Event::assertDispatched(
        DeviceStatusChanged::class,
        fn (DeviceStatusChanged $event): bool => $event->deviceId === $camera->id
            && $event->deviceType === 'camera'
            && $event->assetId === $camera->asset_id
            && $event->status === HardwareStatus::Offline->value,
    );
});

it('skips maintenance devices in markStale', function () {
    $device = Device::factory()->create([
        'status' => HardwareStatus::Maintenance,
        'last_seen_at' => now()->subHours(2),
    ]);

    app(AssetHealthService::class)->markStale();

    expect($device->fresh()->status)->toBe(HardwareStatus::Maintenance);
});

it('keeps maintenance when heartbeat posts online', function () {
    $device = Device::factory()->create([
        'status' => HardwareStatus::Maintenance,
        'api_token_hash' => hash('sha256', 'maint-token'),
        'last_seen_at' => now()->subHour(),
    ]);

    $this->postJson(route('api.devices.heartbeat', $device), [
        'status' => 'online',
    ], [
        'X-Device-Token' => 'maint-token',
    ])->assertOk()
        ->assertJsonPath('data.status', 'maintenance');

    expect($device->fresh()->status)->toBe(HardwareStatus::Maintenance)
        ->and($device->fresh()->last_seen_at?->greaterThan(now()->subMinute()))->toBeTrue();
});

it('forbids hardware settings without view-devices', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($user)
        ->get(route('settings.devices.index'))
        ->assertForbidden();
});

it('updates and retires a camera without hard delete', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $camera = Camera::factory()->create([
        'name' => 'North Cam',
        'stream_url' => 'rtsp://10.0.0.9/stream1',
    ]);

    $this->actingAs($admin)
        ->put(route('settings.cameras.update', $camera), [
            'name' => 'North Cam Updated',
            'stream_url' => 'rtsp://10.0.0.9/stream2',
            'camera_type' => $camera->camera_type->value,
            'reference' => $camera->reference,
            'asset_id' => $camera->asset_id,
        ])
        ->assertRedirect(route('settings.cameras.index'));

    expect($camera->fresh()->name)->toBe('North Cam Updated')
        ->and($camera->fresh()->stream_url)->toBe('rtsp://10.0.0.9/stream2');

    $this->actingAs($admin)
        ->patch(route('settings.cameras.status', $camera), [
            'status' => 'retired',
        ])
        ->assertRedirect();

    expect($camera->fresh()->status)->toBe(HardwareStatus::Retired)
        ->and(Camera::query()->whereKey($camera->id)->exists())->toBeTrue();
});

it('seeds default device credentials onto existing devices', function () {
    $cred = EdgeDeviceCredentials::find('DEV-RFID-01');
    expect($cred)->not->toBeNull();

    $device = Device::factory()->create([
        'reference' => 'DEV-RFID-01',
        'api_token_hash' => hash('sha256', 'stale-token'),
    ]);

    $this->seed(DeviceCredentialsSeeder::class);

    $device->refresh();
    expect($device->uuid)->toBe($cred['uuid'])
        ->and($device->api_token_hash)->toBe(hash('sha256', $cred['token']));

    $this->postJson(route('api.devices.heartbeat', $device), [
        'status' => 'online',
    ], [
        'X-Device-Token' => $cred['token'],
    ])->assertOk();
});

it('retires a device and invalidates its token', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->create([
        'api_token_hash' => hash('sha256', 'dev_oldtoken'),
        'status' => HardwareStatus::Online,
    ]);

    $this->actingAs($admin)
        ->patch(route('settings.devices.status', $device), [
            'status' => 'retired',
        ])
        ->assertRedirect();

    expect($device->fresh()->status)->toBe(HardwareStatus::Retired)
        ->and($device->fresh()->api_token_hash)->toBeNull();
});
