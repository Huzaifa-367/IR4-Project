<?php

namespace App\Http\Requests\Web\Equipment;

use App\Enums\ScheduleType;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncEquipmentSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Equipment $equipment */
        $equipment = $this->route('equipment');

        return $this->user()?->can('manage', $equipment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.schedule_type' => ['required', Rule::enum(ScheduleType::class)],
            'schedules.*.interval_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'schedules.*.notes' => ['nullable', 'string', 'max:150'],
        ];
    }
}
