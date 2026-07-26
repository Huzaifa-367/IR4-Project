<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/storage/private/{path}', function (string $path): StreamedResponse {
    abort_unless(Storage::disk('private')->exists($path), 404);

    return Storage::disk('private')->response($path);
})->where('path', '.*')->middleware('signed')->name('storage.private');
