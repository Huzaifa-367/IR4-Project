<?php

namespace App\Http\Requests\Web\Hse;

use App\Models\LsrViolation;
use Illuminate\Foundation\Http\FormRequest;

final class CloseLsrViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lsr = $this->route('lsr');

        if ($lsr instanceof LsrViolation) {
            return $this->user()?->can('close', $lsr) ?? false;
        }

        return $this->user()?->can('close', LsrViolation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action_taken' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
