<?php

namespace App\Http\Requests\Settings;

use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Device::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('device_type', '');
        if ($type === DeviceType::QrPrinter->value) {
            return;
        }

        $this->merge([
            'printer_host' => null,
            'printer_port' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->input('device_type', '');
        $isCamera = $type === DeviceType::Camera->value;

        return [
            'asset_id' => ['required', 'exists:assets,id'],
            'name' => ['required', 'string', 'max:150'],
            'reference' => ['required', 'string', 'max:150', 'unique:devices,reference'],
            'serial_number' => [
                Rule::excludeIf($isCamera),
                'nullable',
                'string',
                'max:150',
                'unique:devices,serial_number',
            ],
            'device_type' => ['required', Rule::enum(DeviceType::class)],
            'config' => ['nullable', 'array'],
            'issue_token' => ['sometimes', 'boolean'],
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
