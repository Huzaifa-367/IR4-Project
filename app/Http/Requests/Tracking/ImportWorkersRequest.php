<?php

namespace App\Http\Requests\Tracking;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;

final class ImportWorkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Worker::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }
}
