<?php

namespace App\Http\Requests\Web\Equipment;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEquipmentDocumentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ];
    }
}
