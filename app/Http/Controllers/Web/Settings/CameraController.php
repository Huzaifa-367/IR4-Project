<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\CameraType;
use App\Enums\HardwareStatus;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreCameraRequest;
use App\Http\Requests\Settings\UpdateCameraRequest;
use App\Models\Asset;
use App\Models\Camera;
use App\Services\HardwareRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CameraController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Camera::class);

        $query = Camera::query()->with('asset:id,uuid,name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $this->applyListQuery($query, $request, ['name', 'reference', 'status', 'created_at'], ['name', 'reference'], 'name', 'asc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('hardware/cameras/index', [
            'cameras' => [
                'data' => $paginator->getCollection()->map(fn (Camera $camera): array => [
                    'id' => $camera->id,
                    'uuid' => $camera->uuid,
                    'name' => $camera->name,
                    'reference' => $camera->reference,
                    'camera_type' => $camera->camera_type->value,
                    'camera_type_label' => $camera->camera_type->label(),
                    'status' => $camera->status->value,
                    'ai_enabled' => $camera->ai_enabled,
                    'last_frame_at' => $camera->last_frame_at?->toIso8601String(),
                    'stream_url' => $camera->stream_url,
                    'asset' => $camera->asset === null ? null : [
                        'id' => $camera->asset->id,
                        'uuid' => $camera->asset->uuid,
                        'name' => $camera->asset->name,
                    ],
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'assets' => Asset::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'cameraTypes' => collect(CameraType::cases())->map(fn (CameraType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'statuses' => collect(HardwareStatus::cases())->map(fn (HardwareStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
            ],
        ]);
    }

    public function store(StoreCameraRequest $request, HardwareRegistryService $hardware): RedirectResponse
    {
        $hardware->createCamera($request->validated());

        return redirect()->route('settings.cameras.index');
    }

    public function update(UpdateCameraRequest $request, Camera $camera, HardwareRegistryService $hardware): RedirectResponse
    {
        $hardware->updateCamera($camera, $request->validated());

        return redirect()->route('settings.cameras.index');
    }

    public function setStatus(Request $request, Camera $camera, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $camera);

        $data = $request->validate([
            'status' => ['required', Rule::enum(HardwareStatus::class)],
        ]);

        $hardware->setCameraStatus($camera, HardwareStatus::from($data['status']));

        return redirect()->back();
    }

    public function toggleAi(Camera $camera, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $camera);
        $hardware->toggleCameraAi($camera);

        return redirect()->back();
    }
}
