<?php

namespace App\Http\Requests\Web\Permit;

use App\Enums\GasTestPhase;
use App\Enums\GasTestSource;
use App\Models\Permit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordGasTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        return $this->user()?->can('gasTest', $permit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'array'],
            'source' => ['required', 'string', Rule::in(array_column(GasTestSource::cases(), 'value'))],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')],
            'phase' => ['required', 'string', Rule::in(array_column(GasTestPhase::cases(), 'value'))],
        ];
    }
}
