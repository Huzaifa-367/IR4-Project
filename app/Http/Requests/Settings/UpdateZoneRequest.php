<?php

namespace App\Http\Requests\Settings;

use App\Enums\ZoneType;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Zone $zone */
        $zone = $this->route('zone');

        return $this->user()?->can('update', $zone) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'zone_type' => ['sometimes', 'required', Rule::enum(ZoneType::class)],
            'requires_authorization' => ['sometimes', 'boolean'],
            'occupancy_limit' => ['nullable', 'integer', 'min:1'],
            'map_x' => ['nullable', 'numeric'],
            'map_y' => ['nullable', 'numeric'],
            'map_radius' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
