<?php

namespace App\Http\Requests\Api\Ingest;

final class EnvironmentalReadingsIngestRequest extends IngestBatchRequest
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
            'events.*.temperature_c' => ['nullable', 'numeric'],
            'events.*.humidity_pct' => ['nullable', 'numeric'],
            'events.*.wind_speed_ms' => ['nullable', 'numeric'],
            'events.*.extra' => ['nullable', 'array'],
        ];
    }
}
