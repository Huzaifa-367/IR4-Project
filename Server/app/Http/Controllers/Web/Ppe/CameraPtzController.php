<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Live\ControlCameraPtzRequest;
use App\Models\Camera;
use App\Services\Camera\CameraPtzService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Live-wall PTZ API — validates input, delegates to {@see CameraPtzService}.
 */
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

        $ok = match ($validated['action']) {
            'stop' => $ptz->stop($camera, $user),
            'move' => $ptz->move(
                $camera,
                (int) $validated['pan'],
                (int) $validated['tilt'],
                (int) $validated['zoom'],
                $user,
            ),
        };

        if (! $ok) {
            return $this->failedResponse($ptz);
        }

        return ApiResponse::ok(['accepted' => true]);
    }

    private function failedResponse(CameraPtzService $ptz): JsonResponse
    {
        $message = $ptz->lastError() !== '' ? $ptz->lastError() : 'PTZ command failed.';

        return ApiResponse::error('ptz_failed', $message, status: 502);
    }
}
