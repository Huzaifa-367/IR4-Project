<?php

namespace App\Http\Requests\Web\Hse;

use App\Enums\EvidenceType;
use App\Models\HseIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreIncidentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var HseIncident $incident */
        $incident = $this->route('incident');

        return $this->user()?->can('addEvidence', $incident) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evidence_type' => ['required', Rule::enum(EvidenceType::class)],
            'file' => ['nullable', 'file', 'max:51200'],
            'note' => ['nullable', 'string', 'max:5000'],
            'ppe_violation_id' => ['nullable', 'integer', Rule::exists('ppe_violations', 'id')],
            'camera_id' => ['nullable', 'integer', Rule::exists('cameras', 'id')],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
