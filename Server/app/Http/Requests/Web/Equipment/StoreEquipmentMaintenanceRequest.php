<?php

namespace App\Http\Requests\Web\Equipment;

use App\Enums\MaintenanceType;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentMaintenanceRequest extends FormRequest
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
            'performed_at' => ['required', 'date'],
            'maintenance_type' => ['required', Rule::enum(MaintenanceType::class)],
            'description' => ['required', 'string', 'max:5000'],
            'performed_by_name' => ['nullable', 'string', 'max:150'],
            'next_due' => ['nullable', 'date'],
            'return_to_service' => ['sometimes', 'boolean'],
        ];
    }
}
