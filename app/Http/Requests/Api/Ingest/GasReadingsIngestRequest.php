<?php

namespace App\Http\Requests\Api\Ingest;

final class GasReadingsIngestRequest extends IngestBatchRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function eventRules(): array
    {
        return [
            'events.*.event_uid' => ['required', 'uuid'],
            'events.*.device_ref' => ['nullable', 'string', 'max:150'],
            'events.*.recorded_at' => ['required', 'date'],
            'events.*.lel_pct' => ['nullable', 'numeric'],
            'events.*.h2s_ppm' => ['nullable', 'numeric'],
            'events.*.o2_pct' => ['nullable', 'numeric'],
            'events.*.co_ppm' => ['nullable', 'numeric'],
            'events.*.co2_ppm' => ['nullable', 'numeric'],
        ];
    }
}
