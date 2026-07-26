<?php

use App\Models\User;
use App\Services\Backup\ExportAllService;
use Illuminate\Support\Facades\Storage;

it('debug export only', function () {
    Storage::fake('private');
    Storage::fake('exports');
    User::factory()->withRole('Super Admin')->create();
    Storage::disk('private')->put('snapshots/demo.jpg', 'jpeg');
    $export = app(ExportAllService::class)->run('test-client-key');
    expect($export['archive_path'])->not->toBeEmpty();
});
