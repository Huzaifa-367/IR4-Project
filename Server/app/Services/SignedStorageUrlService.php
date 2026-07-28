<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class SignedStorageUrlService
{
    /**
     * Issue a temporary signed URL for a private-disk path (default 15 minutes).
     */
    public function temporaryUrl(string $path, int $minutes = 15, string $disk = 'private'): string
    {
        if (Storage::disk($disk)->providesTemporaryUrls()) {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
        }

        return URL::temporarySignedRoute(
            'storage.private',
            now()->addMinutes($minutes),
            ['path' => $path],
        );
    }
}
