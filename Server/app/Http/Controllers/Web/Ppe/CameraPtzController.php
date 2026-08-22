<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Live\ControlCameraPtzRequest;
use App\Models\Camera;
use App\Services\CameraPtzService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CameraPtzController extends BaseController
{
    public function __invoke(
        ControlCameraPtzRequest $request,
        Camera $camera,
        CameraPtzService $ptz,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validated();

        $ok = $validated['action'] === 'stop'
            ? $ptz->stop($camera, $user)
            : $ptz->move(
                $camera,
                (int) $validated['pan'],
                (int) $validated['tilt'],
                (int) $validated['zoom'],
                $user,
            );

        if (! $ok) {
            return ApiResponse::error(
                'ptz_failed',
                $ptz->lastError() !== '' ? $ptz->lastError() : 'PTZ command failed.',
                status: 502,
            );
        }

        return ApiResponse::ok(['accepted' => true]);
    }
}
