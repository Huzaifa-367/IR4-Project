<?php

namespace App\Http\Controllers\Api\Ingest;

use App\Http\Requests\Api\Ingest\GasReadingsIngestRequest;
use App\Models\Device;
use App\Services\GasMonitoringService;
use App\Services\HardwareRegistryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class GasReadingIngestController
{
    public function __invoke(
        GasReadingsIngestRequest $request,
        GasMonitoringService $gas,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $hardware->recordHeartbeat($device);

        $outcome = $gas->ingestEvents($device, $request->validated('events'));

        return ApiResponse::accepted($outcome);
    }
}
