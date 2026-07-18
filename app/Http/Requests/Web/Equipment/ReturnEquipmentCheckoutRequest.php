<?php

namespace App\Http\Requests\Web\Equipment;

use App\Enums\ReturnStatus;
use App\Models\EquipmentCheckout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReturnEquipmentCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EquipmentCheckout $checkout */
        $checkout = $this->route('checkout');

        return $this->user()?->can('checkout', $checkout->equipment) ?? false;
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
