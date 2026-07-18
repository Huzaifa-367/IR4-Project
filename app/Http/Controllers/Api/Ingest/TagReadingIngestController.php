<?php

namespace App\Http\Controllers\Api\Ingest;

use App\Http\Requests\Api\Ingest\TagReadingsIngestRequest;
use App\Models\Device;
use App\Services\HardwareRegistryService;
use App\Services\TrackingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TagReadingIngestController
{
    public function __invoke(
        TagReadingsIngestRequest $request,
        TrackingService $tracking,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $hardware->recordHeartbeat($device);

        $outcome = $tracking->ingestReadings($device, $request->validated('events'));

        return ApiResponse::accepted($outcome);
    }
}
