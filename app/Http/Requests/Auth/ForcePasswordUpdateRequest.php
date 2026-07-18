<?php

namespace App\Http\Requests\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ForcePasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = $this->user();
                    if ($user !== null && \Illuminate\Support\Facades\Hash::check((string) $value, $user->password)) {
                        $fail('The new password must be different from the current password.');
                    }
                },
            ],
        ];
    }
}
