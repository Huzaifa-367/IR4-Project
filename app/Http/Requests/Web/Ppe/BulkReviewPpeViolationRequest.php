<?php

namespace App\Http\Requests\Web\Ppe;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkReviewPpeViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-ppe-violations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:ppe_violations,id'],
            'status' => ['required', Rule::enum(ReviewStatus::class)->only([
                ReviewStatus::Confirmed,
                ReviewStatus::FalsePositive,
            ])],
            'note' => ['nullable', 'string', 'min:10', 'max:5000'],
        ];
    }
}
