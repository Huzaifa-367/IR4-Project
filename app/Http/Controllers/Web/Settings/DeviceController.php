<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreDeviceRequest;
use App\Http\Requests\Settings\UpdateDeviceRequest;
use App\Models\Asset;
use App\Models\Device;
use App\Services\HardwareRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class DeviceController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Device::class);

        $query = Device::query()->with('asset:id,name');

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->string('device_type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $this->applyListQuery($query, $request, ['name', 'reference', 'device_type', 'status', 'last_seen_at'], ['name', 'reference'], 'name', 'asc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('hardware/devices/index', [
            'devices' => [
                'data' => $paginator->getCollection()->map(fn (Device $device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'reference' => $device->reference,
                    'serial_number' => $device->serial_number,
                    'device_type' => $device->device_type->value,
                    'device_type_label' => $device->device_type->label(),
                    'status' => $device->status->value,
                    'has_token' => $device->api_token_hash !== null,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'asset' => $device->asset === null ? null : [
                        'id' => $device->asset->id,
                        'name' => $device->asset->name,
                    ],
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'deviceTypes' => collect(DeviceType::cases())->map(fn (DeviceType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'statuses' => collect(HardwareStatus::cases())->map(fn (HardwareStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
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
        $hardware->createDevice($request->validated());

        return redirect()->route('settings.devices.index');
    }

    public function update(UpdateDeviceRequest $request, Device $device, HardwareRegistryService $hardware): RedirectResponse
    {
        $hardware->updateDevice($device, $request->validated());

        return redirect()->route('settings.devices.index');
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

    public function regenerateToken(Device $device, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $device);

        $result = $hardware->issueToken($device, request()->user());

        return redirect()
            ->route('settings.devices.index')
            ->with('plain_device_token', [
                'device_id' => $result['device']->id,
                'device_name' => $result['device']->name,
                'token' => $result['plain_token'],
            ]);
    }
}
