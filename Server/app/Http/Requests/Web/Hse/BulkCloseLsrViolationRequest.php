<?php

namespace App\Http\Requests\Web\Hse;

use App\Models\LsrViolation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkCloseLsrViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('close', LsrViolation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('lsr_violations', 'id')],
            'action_taken' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
