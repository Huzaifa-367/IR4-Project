<?php

namespace App\Http\Requests\Web\Hse;

use App\Models\HseIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHseIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HseIncident::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
            'camera_id' => ['nullable', 'integer', Rule::exists('cameras', 'id')],
            'alert_id' => ['nullable', 'integer', Rule::exists('alerts', 'id')],
            'ppe_violation_id' => ['nullable', 'integer', Rule::exists('ppe_violations', 'id')],
            'nature_of_incident' => ['nullable', 'string', 'max:5000'],
            'capture_roster' => ['sometimes', 'boolean'],
        ];
    }
}
