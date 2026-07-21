<?php

namespace App\Http\Requests\Web\Settings;

use App\Support\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->can('update-settings')
            || $user->can('update-alert-settings')
            || $user->can('update-gas-thresholds')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
            'confirmed' => ['sometimes', 'array'],
            'confirmed.*' => ['string', 'in:'.implode(',', SettingsRegistry::keys())],
        ];
    }
}
