<?php

namespace App\Http\Requests\Web\Equipment;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PrintEquipmentLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('printBulk', Equipment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('equipment', 'id')],
        ];
    }
}
