<?php

namespace App\Http\Controllers\Api\Ingest;

use App\Http\Requests\Api\Ingest\PpeViolationsIngestRequest;
use App\Models\Device;
use App\Services\Hardware\HardwareRegistryService;
use App\Services\Ppe\PpeViolationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PpeViolationIngestController
{
    public function __invoke(
        PpeViolationsIngestRequest $request,
        PpeViolationService $ppe,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $hardware->recordHeartbeat($device);

        $outcome = $ppe->ingestEvents($device, $request->validated('events'));

        return ApiResponse::accepted($outcome);
    }
}
