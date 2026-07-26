<?php

namespace App\Http\Requests\Settings;

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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'exists:assets,id'],
            'name' => ['required', 'string', 'max:150'],
            'reference' => ['required', 'string', 'max:150', 'unique:devices,reference'],
            'serial_number' => ['nullable', 'string', 'max:150', 'unique:devices,serial_number'],
            'device_type' => ['required', Rule::enum(DeviceType::class)],
            'config' => ['nullable', 'array'],
        ];
    }
}
