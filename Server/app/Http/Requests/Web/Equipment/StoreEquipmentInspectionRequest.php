<?php

namespace App\Http\Requests\Web\Equipment;

use App\Enums\InspectionOutcome;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Equipment $equipment */
        $equipment = $this->route('equipment');

        return $this->user()?->can('manage', $equipment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inspected_at' => ['required', 'date'],
            'outcome' => ['required', Rule::enum(InspectionOutcome::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_due' => ['nullable', 'date'],
        ];
    }
}
