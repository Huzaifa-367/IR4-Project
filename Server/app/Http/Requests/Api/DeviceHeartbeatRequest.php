<?php

namespace App\Http\Requests\Api;

use App\Enums\HardwareStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeviceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::enum(HardwareStatus::class)->only([
                    HardwareStatus::Online,
                    HardwareStatus::Offline,
                    HardwareStatus::Degraded,
                    HardwareStatus::Fault,
                    HardwareStatus::Maintenance,
                ]),
            ],
            'meta' => ['nullable', 'array'],
        ];
    }
}
