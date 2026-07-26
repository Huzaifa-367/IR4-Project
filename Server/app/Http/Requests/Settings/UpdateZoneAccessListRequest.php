<?php

namespace App\Http\Requests\Settings;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateZoneAccessListRequest extends FormRequest
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
            'worker_ids' => ['sometimes', 'array'],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
        ];
    }
}
