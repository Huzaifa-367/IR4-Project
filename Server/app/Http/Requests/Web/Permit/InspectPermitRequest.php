<?php

namespace App\Http\Requests\Web\Permit;

use App\Models\Permit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InspectPermitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        return $this->user()?->can('inspect', $permit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'as' => ['required', 'string', Rule::in(['issuer', 'receiver'])],
        ];
    }
}
