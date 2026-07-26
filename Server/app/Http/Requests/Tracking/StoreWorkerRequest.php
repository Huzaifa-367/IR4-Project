<?php

namespace App\Http\Requests\Tracking;

use App\Enums\WorkerType;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Worker::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contractor' => ['required', 'string', 'max:150'],
            'worker_type' => ['required', Rule::enum(WorkerType::class)],
            'role_title' => ['nullable', 'string', 'max:150'],
            'badge_number' => ['nullable', 'string', 'max:100', 'unique:workers,badge_number'],
            'employee_code' => ['nullable', 'string', 'max:100', 'unique:workers,employee_code'],
            'phone' => ['nullable', 'string', 'max:40'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
