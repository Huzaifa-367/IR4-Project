<?php

namespace App\Http\Requests\Settings;

use App\Enums\CameraType;
use App\Models\Camera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Camera::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'exists:assets,id'],
            'name' => ['required', 'string', 'max:150'],
            'reference' => ['required', 'string', 'max:150', 'unique:cameras,reference'],
            'camera_type' => ['required', Rule::enum(CameraType::class)],
            'processed_by_device_id' => ['nullable', 'exists:devices,id'],
            'stream_url' => ['required', 'string', 'max:500'],
            'ai_enabled' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
