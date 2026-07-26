<?php

namespace App\Http\Requests\Api\Mobile;

use App\Enums\ReturnStatus;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MobileReturnEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Equipment $equipment */
        $equipment = $this->route('equipment');

        return $this->user()?->can('checkout', $equipment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'return_status' => ['nullable', Rule::enum(ReturnStatus::class)],
            'return_reason' => ['nullable', 'string', 'max:150'],
            'condition_in' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
