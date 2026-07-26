<?php

namespace App\Http\Requests\Api\Ingest;

final class TagReadingsIngestRequest extends IngestBatchRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function eventRules(): array
    {
        return [
            'events.*.event_uid' => ['required', 'uuid'],
            'events.*.reader_ref' => ['required', 'string', 'max:150'],
            'events.*.tag_uid' => ['required', 'string', 'max:150'],
            'events.*.recorded_at' => ['required', 'date'],
            'events.*.rssi' => ['nullable', 'numeric'],
        ];
    }
}
