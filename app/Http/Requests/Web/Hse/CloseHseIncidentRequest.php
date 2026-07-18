<?php

namespace App\Http\Requests\Web\Hse;

use App\Models\HseIncident;
use Illuminate\Foundation\Http\FormRequest;

final class CloseHseIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var HseIncident $incident */
        $incident = $this->route('incident');

        return $this->user()?->can('close', $incident) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'close_note' => ['nullable', 'string', 'min:10', 'max:5000'],
        ];
    }
}
