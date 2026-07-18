<?php

namespace App\Http\Requests\Settings;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Asset $asset */
        $asset = $this->route('asset');

        return $this->user()?->can('update', $asset) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Asset $asset */
        $asset = $this->route('asset');

        return [
            'asset_type' => ['sometimes', 'required', Rule::enum(AssetType::class)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'identifier' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('assets', 'identifier')->ignore($asset->id),
            ],
            'status' => ['sometimes', Rule::enum(AssetStatus::class)],
            'is_mobile' => ['sometimes', 'boolean'],
            'current_location_label' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
