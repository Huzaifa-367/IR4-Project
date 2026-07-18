<?php

namespace App\Http\Requests\Web\Ppe;

use App\Enums\ReviewStatus;
use App\Models\PpeViolation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewPpeViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PpeViolation $violation */
        $violation = $this->route('violation');

        return $this->user()?->can('review', $violation) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReviewStatus::class)->only([
                ReviewStatus::Confirmed,
                ReviewStatus::FalsePositive,
            ])],
            'note' => ['nullable', 'string', 'min:10', 'max:5000'],
        ];
    }
}
