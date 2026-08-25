<?php

namespace App\Support\Ingest;

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

    public function resolveCamera(string $reference): ?Device
    {
        return Device::query()->cameras()->where('reference', $reference)->first();
    }
}
