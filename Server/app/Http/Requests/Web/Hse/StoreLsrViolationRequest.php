<?php

namespace App\Http\Requests\Web\Hse;

use App\Enums\LsrCategory;
use App\Models\LsrViolation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLsrViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LsrViolation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(LsrCategory::class)],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
            'camera_id' => ['nullable', 'integer', Rule::exists('cameras', 'id')],
            'alert_id' => ['nullable', 'integer', Rule::exists('alerts', 'id')],
            'ppe_violation_id' => ['nullable', 'integer', Rule::exists('ppe_violations', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
