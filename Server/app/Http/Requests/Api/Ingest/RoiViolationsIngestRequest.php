<?php

namespace App\Http\Requests\Api\Ingest;

use Illuminate\Validation\Rule;

final class RoiViolationsIngestRequest extends IngestBatchRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function eventRules(): array
    {
        return [
            'events.*.event_uid' => ['required', 'uuid'],
            'events.*.camera_ref' => ['required', 'string', 'max:150'],
            'events.*.roi_reference' => ['required', 'string', 'max:63'],
            'events.*.event_type' => [
                'required',
                'string',
                Rule::in(['roi_intrusion']),
            ],
            'events.*.detected_at' => ['required', 'date'],
            'events.*.confidence' => ['required', 'numeric', 'min:0', 'max:1'],
            'events.*.snapshot' => ['nullable', 'string'],
        ];
    }
}
