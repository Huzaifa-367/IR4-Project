<?php

namespace App\Http\Controllers\Api\Ingest;

use App\Http\Requests\Api\Ingest\EnvironmentalReadingsIngestRequest;
use App\Models\Device;
use App\Services\EnvironmentalDataService;
use App\Services\HardwareRegistryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class EnvironmentalReadingIngestController
{
    public function __invoke(
        EnvironmentalReadingsIngestRequest $request,
        EnvironmentalDataService $environment,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $hardware->recordHeartbeat($device);

        $outcome = $environment->ingestEvents($device, $request->validated('events'));

        return ApiResponse::accepted($outcome);
    }
}
