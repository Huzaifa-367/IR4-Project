<?php

namespace App\Http\Requests\Settings;

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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Device $device */
        $device = $this->route('device');

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
                'nullable',
                'string',
                'max:150',
                Rule::unique('devices', 'serial_number')->ignore($device->id),
            ],
            'device_type' => ['sometimes', 'required', Rule::enum(DeviceType::class)],
            'config' => ['nullable', 'array'],
        ];
    }
}
