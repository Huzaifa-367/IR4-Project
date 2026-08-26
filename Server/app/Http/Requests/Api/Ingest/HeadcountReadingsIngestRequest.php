<?php

namespace App\Http\Requests\Api\Ingest;

final class HeadcountReadingsIngestRequest extends IngestBatchRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function eventRules(): array
    {
        return [
            'events.*.event_uid' => ['required', 'uuid'],
            'events.*.recorded_at' => ['required', 'date'],
            'events.*.count' => ['required', 'integer', 'min:0', 'max:100000'],
            'events.*.camera_ref' => ['required', 'string', 'max:150'],
        ];
    }
}
