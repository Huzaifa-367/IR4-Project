<?php

namespace App\Http\Requests\Web\Reports;

use App\Services\VehicleViolationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreVehicleViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-vehicle-violations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'vehicle_description' => ['required', 'string', 'max:255'],
            'violation_type' => ['required', 'string', 'max:100', Rule::in(VehicleViolationService::violationTypes())],
            'description' => ['nullable', 'string', 'max:2000'],
            'action_taken' => ['required', 'string', 'min:10', 'max:5000'],
            'camera_id' => ['nullable', 'integer', Rule::exists('cameras', 'id')],
        ];
    }
}
