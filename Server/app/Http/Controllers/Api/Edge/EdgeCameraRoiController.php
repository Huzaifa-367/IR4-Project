<?php

namespace App\Http\Controllers\Api\Edge;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Camera\CameraRoiService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EdgeCameraRoiController extends Controller
{
    public function __invoke(Request $request, Device $device, CameraRoiService $rois): JsonResponse
    {
        /** @var Device|null $caller */
        $caller = $request->attributes->get('device');
        abort_if($caller === null, 401);

        if ($caller->id !== $device->id) {
            return ApiResponse::error('FORBIDDEN', 'Token does not match device.', status: 403);
        }

        return ApiResponse::ok($rois->activePayloadForEdgeDevice($device));
    }
}
