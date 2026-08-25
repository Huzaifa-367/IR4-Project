<?php

namespace App\Http\Requests\Web\CameraRoi;

use Illuminate\Foundation\Http\FormRequest;

final class SaveCameraRoisRequest extends FormRequest
{
    use CameraRoiPayloadRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-camera-rois') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRoiPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->roiPayloadRules(roisRequired: true);
    }
}
