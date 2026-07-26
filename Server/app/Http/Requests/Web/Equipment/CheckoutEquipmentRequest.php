<?php

namespace App\Http\Requests\Web\Equipment;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutEquipmentRequest extends FormRequest
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
            'worker_id' => ['required', 'integer', Rule::exists('workers', 'id')],
            'reason' => ['nullable', 'string', 'max:150'],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
            'expected_return_at' => ['nullable', 'date'],
            'condition_out' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
