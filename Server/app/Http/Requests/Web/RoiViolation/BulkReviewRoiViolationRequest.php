<?php

namespace App\Http\Requests\Web\RoiViolation;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkReviewRoiViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-roi-violations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:roi_violations,id'],
            'status' => ['required', Rule::enum(ReviewStatus::class)->only([
                ReviewStatus::Confirmed,
                ReviewStatus::FalsePositive,
            ])],
            'note' => ['nullable', 'string', 'min:10', 'max:5000'],
        ];
    }
}
