<?php

namespace App\Support\Ingest;

use App\Services\Storage\SignedStorageUrlService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared base64 → private-disk snapshot store for device ingest (PPE, ROI, …).
 * Path contract: snapshots/{Y/m/d}/{uuid}.jpg on the private disk (DOC-01 / DOC-10).
 */
final class IngestSnapshotStore
{
    public function __construct(
        private readonly SignedStorageUrlService $signedUrls,
    ) {}

    public function store(?string $base64): ?string
    {
        if ($base64 === null || $base64 === '') {
            return null;
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $path = 'snapshots/'.now()->format('Y/m/d').'/'.Str::uuid().'.jpg';
        Storage::disk('private')->put($path, $decoded);

        return $path;
    }

    public function temporaryUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return $this->signedUrls->temporaryUrl($path);
    }
}
