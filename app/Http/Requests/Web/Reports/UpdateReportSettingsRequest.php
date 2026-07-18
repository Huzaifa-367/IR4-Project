<?php

namespace App\Http\Requests\Web\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateReportSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_publish' => $this->boolean('auto_publish'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'generation_day' => ['required', 'string', Rule::in([
                'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ])],
            'generation_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'auto_publish' => ['required', 'boolean'],
            'week_start' => ['sometimes', 'string', Rule::in([
                'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ])],
            'completeness_threshold_pct' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
