<?php

namespace App\Http\Requests\Settings;

use App\Models\Role;
use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->user()?->can('update', $role) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_read_only' => ['sometimes', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalogue::all())],
        ];
    }
}
