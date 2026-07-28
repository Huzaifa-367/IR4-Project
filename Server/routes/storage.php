<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Use /files/private/... (not /storage/...) so Hostinger root .htaccess can keep
 * denying the real on-disk `storage/` tree without blocking signed downloads.
 */
Route::get('/files/private/{path}', function (string $path): StreamedResponse {
    $path = rawurldecode($path);
    abort_unless(Storage::disk('private')->exists($path), 404);

    return Storage::disk('private')->response($path);
})->where('path', '.*')->middleware('signed')->name('storage.private');
