<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreDeviceRequest;
use App\Http\Requests\Settings\UpdateDeviceRequest;
use App\Models\Asset;
use App\Models\Device;
use App\Services\Hardware\AssetHealthService;
use App\Services\Hardware\HardwareRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class DeviceController extends BaseController
{
    public function index(Request $request, HardwareRegistryService $hardware, AssetHealthService $health): Response
    {
        $this->authorize('viewAny', Device::class);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));
        $page = max(1, (int) $request->integer('page', 1));
        $request->merge(['page' => $page, 'per_page' => $perPage]);

        $result = $hardware->registryRows($request, $health);
        $lastPage = max(1, (int) ceil($result['total'] / $perPage));

        return Inertia::render('hardware/devices/index', [
            'rows' => [
                'data' => $result['data'],
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'total' => $result['total'],
                    'per_page' => $perPage,
                ],
            ],
            'assets' => Asset::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'registryTypes' => collect(DeviceType::registryTypes())->map(fn (DeviceType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->all(),
            'cameraTypes' => collect(CameraType::cases())->map(fn (CameraType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'statuses' => collect(HardwareStatus::cases())->map(fn (HardwareStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'plainToken' => $request->session()->pull('plain_device_token'),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'device_type' => $request->string('device_type')->toString(),
                'status' => $request->string('status')->toString(),
            ],
        ]);
    }

    public function store(StoreDeviceRequest $request, HardwareRegistryService $hardware): RedirectResponse
    {
        $result = $hardware->createDevice($request->validated(), $request->user());
        $device = $result['device'];

        $redirect = redirect()->route(
            'settings.devices.index',
            $device->isCamera() ? ['device_type' => 'camera'] : [],
        );

        if (($result['plain_token'] ?? null) !== null && $device->isCamera()) {
            $redirect->with('plain_device_token', [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'token' => $result['plain_token'],
            ]);
        }

        return $redirect;
    }

    public function update(UpdateDeviceRequest $request, Device $device, HardwareRegistryService $hardware): RedirectResponse
    {
        $hardware->updateDevice($device, $request->validated());

        return redirect()->route(
            'settings.devices.index',
            $device->isCamera() ? ['device_type' => 'camera'] : [],
        );
    }

    public function setStatus(Request $request, Device $device, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $device);

        $data = $request->validate([
            'status' => ['required', Rule::enum(HardwareStatus::class)],
        ]);

        $hardware->setDeviceStatus($device, HardwareStatus::from($data['status']));

        return redirect()->back();
    }

    public function toggleAi(Device $device, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $device);
        $hardware->toggleCameraAi($device);

        return redirect()->back();
    }

    public function regenerateToken(Device $device, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $device);

        $result = $hardware->issueToken($device, request()->user());

        return redirect()
            ->route(
                'settings.devices.index',
                $device->isCamera() ? ['device_type' => 'camera'] : [],
            )
            ->with('plain_device_token', [
                'device_id' => $result['device']->id,
                'device_name' => $result['device']->name,
                'token' => $result['plain_token'],
            ]);
    }
}
