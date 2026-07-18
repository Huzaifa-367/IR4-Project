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
            $user->can('manage-settings')
            || $user->can('configure-alerts')
            || $user->can('manage-gas-thresholds')
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
