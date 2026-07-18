<?php

namespace App\Http\Requests\Web\Equipment;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;

final class ImportEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Equipment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }
}
