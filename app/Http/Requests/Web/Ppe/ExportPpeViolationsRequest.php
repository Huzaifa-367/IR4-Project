<?php

namespace App\Http\Requests\Web\Ppe;

use Illuminate\Foundation\Http\FormRequest;

final class ExportPpeViolationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export-ppe-violations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'in:csv,pdf'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }
}
