<?php

namespace App\Http\Requests\Tracking;

use App\Enums\WorkerType;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Worker $worker */
        $worker = $this->route('worker');

        return $this->user()?->can('update', $worker) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Worker $worker */
        $worker = $this->route('worker');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'contractor' => ['sometimes', 'required', 'string', 'max:150'],
            'worker_type' => ['sometimes', 'required', Rule::enum(WorkerType::class)],
            'role_title' => ['nullable', 'string', 'max:150'],
            'badge_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('workers', 'badge_number')->ignore($worker->id),
            ],
            'employee_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('workers', 'employee_code')->ignore($worker->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
