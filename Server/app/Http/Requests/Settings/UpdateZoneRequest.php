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
            'requires_permit' => ['sometimes', 'boolean'],
            'occupancy_limit' => ['nullable', 'integer', 'min:1'],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
