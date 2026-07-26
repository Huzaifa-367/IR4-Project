<?php

namespace App\Http\Requests\Settings;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Asset::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_type' => ['required', Rule::enum(AssetType::class)],
            'name' => ['required', 'string', 'max:150'],
            'identifier' => ['required', 'string', 'max:150', 'unique:assets,identifier'],
            'status' => ['sometimes', Rule::enum(AssetStatus::class)],
            'is_mobile' => ['sometimes', 'boolean'],
            'current_location_label' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
