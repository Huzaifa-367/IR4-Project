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
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx',
                // 15 MB (Laravel file max is kilobytes).
                'max:15360',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The worker import file may not be larger than 15 MB.',
        ];
    }
}
