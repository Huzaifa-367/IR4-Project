<?php

namespace App\Http\Requests\Web\Permit;

use App\Models\Permit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePermitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permit_type_id' => ['required', 'integer', Rule::exists('permit_types', 'id')],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
            'task_description' => ['required', 'string', 'max:2000'],
            'personnel' => ['nullable', 'array'],
            'personnel.*.worker_id' => ['required', 'integer', Rule::exists('workers', 'id')],
            'personnel.*.role_code' => ['required', 'string', 'max:64'],
            'checklist' => ['nullable', 'array'],
            'is_extended' => ['sometimes', 'boolean'],
        ];
    }
}
