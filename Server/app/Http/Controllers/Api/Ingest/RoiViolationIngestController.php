<?php

namespace App\Http\Controllers\Api\Ingest;

use App\Http\Requests\Api\Ingest\RoiViolationsIngestRequest;
use App\Models\Device;
use App\Services\Camera\RoiViolationService;
use App\Services\Hardware\HardwareRegistryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RoiViolationIngestController
{
    public function __invoke(
        RoiViolationsIngestRequest $request,
        RoiViolationService $rois,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $hardware->recordHeartbeat($device);

        $outcome = $rois->ingestEvents($device, $request->validated('events'));

        return ApiResponse::accepted($outcome);
    }
}
