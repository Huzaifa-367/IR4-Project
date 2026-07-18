<?php

namespace App\Http\Requests\Web\Hse;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\Involvement;
use App\Models\HseIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClassifyHseIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var HseIncident $incident */
        $incident = $this->route('incident');

        return $this->user()?->can('classify', $incident) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incident_type' => ['required', Rule::enum(IncidentType::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'nature_of_incident' => ['required', 'string', 'min:10', 'max:5000'],
            'immediate_action' => ['required', 'string', 'min:10', 'max:5000'],
            'corrective_action' => ['required', 'string', 'min:10', 'max:5000'],
            'personnel' => ['sometimes', 'array'],
            'personnel.*.worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')],
            'personnel.*.involvement' => ['required_with:personnel.*.worker_id', Rule::in([
                Involvement::Involved->value,
                Involvement::Witness->value,
            ])],
        ];
    }
}
