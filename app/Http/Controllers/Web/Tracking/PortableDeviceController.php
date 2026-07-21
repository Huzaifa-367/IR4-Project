<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
use App\Models\PortableDevice;
use App\Models\Worker;
use App\Services\PortableDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortableDeviceController extends BaseController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view-portable-devices'), 403);

        $query = PortableDevice::query()->with('worker');
        $this->applyListQuery(
            $query,
            $request,
            sortable: ['device_type', 'status', 'created_at'],
            searchable: ['device_type', 'serial_number', 'make_model'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('workforce/portable-devices/index', [
            'devices' => [
                'data' => $paginator->getCollection()->map(fn (PortableDevice $d) => [
                    'id' => $d->id,
                    'worker_id' => $d->worker_id,
                    'worker_name' => $d->worker?->name,
                    'device_type' => $d->device_type,
                    'make_model' => $d->make_model,
                    'serial_number' => $d->serial_number,
                    'status' => $d->status->value,
                    'approved_at' => $d->approved_at?->toIso8601String(),
                    'revoked_at' => $d->revoked_at?->toIso8601String(),
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'workers' => Worker::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, PortableDeviceService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('create-portable-devices'), 403);

        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'device_type' => ['required', 'string', 'max:150'],
            'make_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'approval_reference' => ['nullable', 'string', 'max:150'],
        ]);

        $service->create(
            Worker::query()->findOrFail($data['worker_id']),
            $data,
            $request->user(),
        );

        return redirect()->back();
    }

    public function revoke(Request $request, PortableDevice $portableDevice, PortableDeviceService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('update-portable-devices'), 403);

        $data = $request->validate([
            'revoke_reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $service->revoke($portableDevice, $data['revoke_reason'], $request->user());

        return redirect()->back();
    }
}
