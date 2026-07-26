<?php

namespace App\Http\Requests\Web\Gas;

use App\Enums\GasType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGasThresholdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-gas-thresholds') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'thresholds' => ['required', 'array', 'min:1'],
            'thresholds.*.gas_type' => ['required', Rule::enum(GasType::class)],
            'thresholds.*.warning_level' => ['required', 'numeric'],
            'thresholds.*.alarm_level' => ['required', 'numeric'],
        ];
    }
}
