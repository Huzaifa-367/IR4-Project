<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\CameraType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreCameraRequest;
use App\Models\Asset;
use App\Models\Camera;
use App\Services\HardwareRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CameraController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Camera::class);

        $query = Camera::query()->with('asset:id,name');

        $this->applyListQuery($query, $request, ['name', 'reference', 'status', 'created_at'], ['name', 'reference'], 'name', 'asc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('settings/cameras/index', [
            'cameras' => [
                'data' => $paginator->getCollection()->map(fn (Camera $camera): array => [
                    'id' => $camera->id,
                    'name' => $camera->name,
                    'reference' => $camera->reference,
                    'camera_type' => $camera->camera_type->value,
                    'status' => $camera->status->value,
                    'ai_enabled' => $camera->ai_enabled,
                    'last_frame_at' => $camera->last_frame_at?->toIso8601String(),
                    'asset' => $camera->asset === null ? null : [
                        'id' => $camera->asset->id,
                        'name' => $camera->asset->name,
                    ],
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'cameraTypes' => collect(CameraType::cases())->map(fn (CameraType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreCameraRequest $request, HardwareRegistryService $hardware): RedirectResponse
    {
        $hardware->createCamera($request->validated());

        return redirect()->route('settings.cameras.index');
    }

    public function toggleAi(Camera $camera, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('update', $camera);
        $hardware->toggleCameraAi($camera);

        return redirect()->back();
    }
}
