<?php

namespace App\Http\Controllers\Api\Ingest;

use App\Http\Requests\Api\Ingest\HeadcountReadingsIngestRequest;
use App\Models\Device;
use App\Services\Hardware\HardwareRegistryService;
use App\Services\Tracking\HeadcountIngestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class HeadcountReadingIngestController
{
    public function __invoke(
        HeadcountReadingsIngestRequest $request,
        HeadcountIngestService $headcounts,
        HardwareRegistryService $hardware,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $hardware->recordHeartbeat($device);

        $outcome = $headcounts->ingestEvents($device, $request->validated('events'));

        return ApiResponse::accepted($outcome);
    }
}
