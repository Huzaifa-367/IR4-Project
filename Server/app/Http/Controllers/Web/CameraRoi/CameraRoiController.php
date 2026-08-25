<?php

namespace App\Http\Controllers\Web\CameraRoi;

use App\Enums\CameraRoiStaleReason;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\CameraRoi\PublishCameraRoisRequest;
use App\Http\Requests\Web\CameraRoi\SaveCameraRoisRequest;
use App\Models\Camera;
use App\Services\Camera\CameraRoiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class CameraRoiController extends BaseController
{
    public function index(Request $request, CameraRoiService $rois): InertiaResponse
    {
        return Inertia::render('camera-rois/index', [
            'cameras' => $rois->indexRows(),
            'canManage' => $request->user()?->can('manage-camera-rois') ?? false,
        ]);
    }

    public function edit(Request $request, Camera $camera, CameraRoiService $rois): InertiaResponse
    {
        return Inertia::render('camera-rois/edit', [
            ...$rois->editorPayload($camera),
            'canManage' => $request->user()?->can('manage-camera-rois') ?? false,
        ]);
    }

    public function update(
        SaveCameraRoisRequest $request,
        Camera $camera,
        CameraRoiService $rois,
    ): RedirectResponse {
        $rois->saveDraft($camera, $request->validated('rois'), $request->user());

        return redirect()
            ->route('settings.camera-rois.edit', $camera)
            ->with('success', 'ROI draft saved.');
    }

    public function publish(
        PublishCameraRoisRequest $request,
        Camera $camera,
        CameraRoiService $rois,
    ): RedirectResponse {
        $payload = $request->validated();
        $rois->publish(
            $camera,
            isset($payload['rois']) ? $payload['rois'] : null,
            $request->user(),
        );

        return redirect()
            ->route('settings.camera-rois.edit', $camera)
            ->with('success', 'ROIs published for edge AI.');
    }

    public function markStale(
        Request $request,
        Camera $camera,
        CameraRoiService $rois,
    ): RedirectResponse {
        abort_unless($request->user()?->can('manage-camera-rois'), 403);

        $rois->markStale($camera, CameraRoiStaleReason::Manual, $request->user());

        return redirect()
            ->route('settings.camera-rois.edit', $camera)
            ->with('success', 'ROI set marked stale.');
    }
}
