<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class SignedStorageUrlService
{
    /**
     * Issue a temporary signed URL for a private-disk path (default 15 minutes).
     *
     * Always uses the app `storage.private` route for the private disk so URLs
     * stay under /storage/private/... (not Laravel's local "serve" URL shape).
     */
    public function temporaryUrl(string $path, int $minutes = 15, string $disk = 'private'): string
    {
        if ($disk !== 'private' && Storage::disk($disk)->providesTemporaryUrls()) {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
        }

        return URL::temporarySignedRoute(
            'storage.private',
            now()->addMinutes($minutes),
            ['path' => ltrim($path, '/')],
        );
    }
}
