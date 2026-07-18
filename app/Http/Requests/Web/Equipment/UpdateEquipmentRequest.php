<?php

namespace App\Http\Requests\Web\Equipment;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Equipment $equipment */
        $equipment = $this->route('equipment');

        return $this->user()?->can('update', $equipment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Equipment $equipment */
        $equipment = $this->route('equipment');

        return [
            'equipment_code' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('equipment', 'equipment_code')->ignore($equipment->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'equipment_type' => ['sometimes', 'required', 'string', 'max:150'],
            'status' => ['sometimes', 'nullable', Rule::enum(EquipmentStatus::class)],
            'is_checkoutable' => ['sometimes', 'boolean'],
            'location_label' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'inspection_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'service_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
