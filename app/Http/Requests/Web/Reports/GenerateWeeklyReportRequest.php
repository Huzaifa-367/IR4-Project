<?php

namespace App\Http\Requests\Web\Reports;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateWeeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('generate-reports') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }
}
