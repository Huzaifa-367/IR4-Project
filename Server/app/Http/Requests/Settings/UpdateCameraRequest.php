<?php

namespace App\Http\Requests\Settings;

use App\Enums\CameraType;
use App\Enums\HardwareStatus;
use App\Models\Camera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Camera $camera */
        $camera = $this->route('camera');

        return $this->user()?->can('update', $camera) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Camera $camera */
        $camera = $this->route('camera');

        return [
            'asset_id' => ['sometimes', 'required', 'exists:assets,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('cameras', 'reference')->ignore($camera->id),
            ],
            'camera_type' => ['sometimes', 'required', Rule::enum(CameraType::class)],
            'processed_by_device_id' => ['nullable', 'exists:devices,id'],
            'stream_url' => ['sometimes', 'required', 'string', 'max:500'],
            'ai_enabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(HardwareStatus::class)],
            'meta' => ['nullable', 'array'],
        ];
    }
}
