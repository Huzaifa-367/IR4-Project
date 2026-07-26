<?php

namespace App\Http\Requests\Settings;

use App\Models\Role;
use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_read_only' => ['sometimes', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalogue::all())],
        ];
    }
}
