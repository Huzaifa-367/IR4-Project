<?php

namespace App\Http\Requests\Web\CameraRoi;

trait CameraRoiPayloadRules
{
    protected function prepareRoiPayload(): void
    {
        $rois = $this->input('rois');
        if (! is_array($rois)) {
            return;
        }

        foreach ($rois as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['reference'] ?? null) === '') {
                $rois[$index]['reference'] = null;
            }
        }

        $this->merge(['rois' => $rois]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function roiPayloadRules(bool $roisRequired): array
    {
        $rois = $roisRequired
            ? ['required', 'array', 'min:1']
            : ['sometimes', 'array', 'min:1'];

        return [
            'rois' => $rois,
            'rois.*.name' => ['required_with:rois', 'string', 'max:120'],
            'rois.*.reference' => [
                'nullable',
                'string',
                'max:63',
                'regex:/^[a-z0-9][a-z0-9_-]{1,62}$/',
            ],
            'rois.*.polygon' => ['required_with:rois', 'array', 'min:3'],
            'rois.*.polygon.*.x' => ['required_with:rois', 'numeric', 'between:0,1'],
            'rois.*.polygon.*.y' => ['required_with:rois', 'numeric', 'between:0,1'],
            'rois.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'rois.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rois.*.is_enabled' => ['nullable', 'boolean'],
            'rois.*.meta' => ['nullable', 'array'],
        ];
    }
}
