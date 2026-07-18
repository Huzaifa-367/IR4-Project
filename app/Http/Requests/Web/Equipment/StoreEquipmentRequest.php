<?php

namespace App\Http\Requests\Web\Equipment;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Equipment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipment_code' => ['nullable', 'string', 'max:150', Rule::unique('equipment', 'equipment_code')],
            'name' => ['required', 'string', 'max:150'],
            'equipment_type' => ['required', 'string', 'max:150'],
            'is_checkoutable' => ['sometimes', 'boolean'],
            'location_label' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'inspection_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'service_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'next_inspection_due' => ['nullable', 'date'],
            'next_service_due' => ['nullable', 'date'],
        ];
    }
}
