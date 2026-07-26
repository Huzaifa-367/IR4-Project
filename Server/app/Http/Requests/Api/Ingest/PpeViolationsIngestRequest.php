<?php

namespace App\Http\Requests\Api\Ingest;

use Illuminate\Validation\Rule;

final class PpeViolationsIngestRequest extends IngestBatchRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function eventRules(): array
    {
        return [
            'events.*.event_uid' => ['required', 'uuid'],
            'events.*.camera_ref' => ['required', 'string', 'max:150'],
            'events.*.event_type' => [
                'required',
                'string',
                Rule::in([
                    'missing_helmet',
                    'missing_vest',
                    'missing_harness',
                    'missing_mask',
                    'fall',
                ]),
            ],
            'events.*.detected_at' => ['required', 'date'],
            'events.*.worker_count' => ['nullable', 'integer', 'min:0'],
            'events.*.confidence' => ['required', 'numeric', 'min:0', 'max:1'],
            'events.*.snapshot' => ['nullable', 'string'],
        ];
    }
}
