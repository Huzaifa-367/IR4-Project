<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => [
                'sometimes',
                'string',
                Rule::exists('roles', 'name')->where(fn ($q) => $q->where('guard_name', PermissionCatalogue::GUARD)),
            ],
        ];
    }
}
