<?php

namespace App\Http\Requests\Web\Live;

use App\Models\Camera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlCameraPtzRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Camera $camera */
        $camera = $this->route('camera');

        return $this->user()?->can('controlPtz', $camera) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['move', 'stop'])],
            'pan' => ['required_if:action,move', 'integer', 'min:-100', 'max:100'],
            'tilt' => ['required_if:action,move', 'integer', 'min:-100', 'max:100'],
            'zoom' => ['required_if:action,move', 'integer', 'min:-100', 'max:100'],
        ];
    }
}
