<?php

namespace App\Http\Requests\Settings;

use App\Enums\ZoneType;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Zone::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'zone_type' => ['required', Rule::enum(ZoneType::class)],
            'requires_authorization' => ['sometimes', 'boolean'],
            'requires_permit' => ['sometimes', 'boolean'],
            'occupancy_limit' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
