<?php

namespace App\Http\Requests\Web\Permit;

use App\Models\Permit;
use Illuminate\Foundation\Http\FormRequest;

final class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return match ($this->route()?->getName()) {
            'permits.suspend' => $user->can('suspend', $permit),
            'permits.cancel' => $user->can('cancel', $permit),
            'permits.close' => $user->can('close', $permit),
            'permits.reject' => $user->can('reject', $permit),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
