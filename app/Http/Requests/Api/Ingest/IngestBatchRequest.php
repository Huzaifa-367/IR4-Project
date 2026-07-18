<?php

namespace App\Http\Requests\Api\Ingest;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

abstract class IngestBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) app(SettingsService::class)->get('ingest.max_batch', 1000);

        return array_merge([
            'events' => ['required', 'array', 'min:1', "max:{$max}"],
        ], $this->eventRules());
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function eventRules(): array;
}
