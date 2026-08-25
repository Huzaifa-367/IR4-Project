<?php

namespace App\Http\Requests\Settings;

use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Device $device */
        $device = $this->route('device');

        return $this->user()?->can('update', $device) ?? false;
    }

    protected function prepareForValidation(): void
    {
        /** @var Device $device */
        $device = $this->route('device');
        $type = (string) ($this->input('device_type') ?? $device->device_type->value);

        if ($type !== DeviceType::QrPrinter->value) {
            $this->merge([
                'printer_host' => null,
                'printer_port' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Device $device */
        $device = $this->route('device');
        $type = (string) ($this->input('device_type') ?? $device->device_type->value);
        $isCamera = $device->isCamera() || $type === DeviceType::Camera->value;

        return [
            'asset_id' => ['sometimes', 'required', 'exists:assets,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('devices', 'reference')->ignore($device->id),
            ],
            'serial_number' => [
                Rule::excludeIf($isCamera),
                'nullable',
                'string',
                'max:150',
                Rule::unique('devices', 'serial_number')->ignore($device->id),
            ],
            'device_type' => [
                'sometimes',
                'required',
                Rule::enum(DeviceType::class),
            ],
            'config' => ['nullable', 'array'],
            'camera_type' => [
                Rule::requiredIf($isCamera),
                Rule::enum(CameraType::class),
            ],
            'stream_url' => [
                Rule::requiredIf($isCamera),
                'string',
                'max:500',
            ],
            'ai_enabled' => ['sometimes', 'boolean'],
            'api_url' => [
                Rule::requiredIf($isCamera),
                'string',
                'max:255',
                'regex:/^(https?:\/\/)?[^\s]+$/i',
            ],
            'printer_host' => [
                Rule::requiredIf($type === DeviceType::QrPrinter->value),
                'nullable',
                'string',
                'max:255',
            ],
            'printer_port' => [
                Rule::requiredIf($type === DeviceType::QrPrinter->value),
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],
        ];
    }
}
