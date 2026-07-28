<?php

use App\Services\SignedStorageUrlService;
use Illuminate\Support\Facades\Storage;

it('issues signed urls under /files/private not /storage', function () {
    Storage::fake('private');
    Storage::disk('private')->put('reports/19/report.pdf', '%PDF-1.4 test');

    $url = app(SignedStorageUrlService::class)->temporaryUrl('reports/19/report.pdf', 15);

    expect($url)->toContain('/files/private/reports/19/report.pdf')
        ->and($url)->not->toContain('/storage/private/')
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=');

    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    $this->get($path)->assertForbidden();

    $this->get($path.'?'.$query)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('returns 404 when the signed private file is missing', function () {
    Storage::fake('private');

    $url = app(SignedStorageUrlService::class)->temporaryUrl('reports/999/missing.pdf', 15);
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    $this->get($path.'?'.$query)->assertNotFound();
});
