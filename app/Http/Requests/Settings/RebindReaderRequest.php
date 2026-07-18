<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class RebindReaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-zones') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'exists:zones,id'],
            'effective_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'asset_location_label' => ['nullable', 'string', 'max:255'],
            'confirm_gate_rebind' => ['sometimes', 'boolean'],
        ];
    }
}
