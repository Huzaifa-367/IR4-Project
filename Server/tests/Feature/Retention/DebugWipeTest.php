<?php

use App\Models\User;
use App\Services\Backup\ExportAllService;
use App\Services\Backup\SecureWipeService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('backups');
    Storage::fake('exports');
});

it('debug wipe in feature folder', function () {
    User::factory()->withRole('Super Admin')->create();
    Storage::disk('private')->put('snapshots/demo.jpg', 'jpeg');
    $export = app(ExportAllService::class)->run('test-client-key');
    $result = app(SecureWipeService::class)->wipe(
        SecureWipeService::CONFIRM_PHRASE,
        $export['export_id'],
    );
    expect($result['receipt'])->not->toBeEmpty();
});
