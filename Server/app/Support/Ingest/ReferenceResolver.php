<?php

namespace App\Support\Ingest;

use App\Models\Camera;
use App\Models\Device;

final class ReferenceResolver
{
    public function resolveDevice(string $reference): ?Device
    {
        return Device::query()->where('reference', $reference)->first();
    }

    public function resolveReader(string $reference): ?Device
    {
        return $this->resolveDevice($reference);
    }

    public function resolveCamera(string $reference): ?Camera
    {
        return Camera::query()->where('reference', $reference)->first();
    }
}
