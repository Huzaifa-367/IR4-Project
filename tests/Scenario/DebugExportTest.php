<?php

it('debug export only', function () {
    \Illuminate\Support\Facades\Storage::fake('private');
    \Illuminate\Support\Facades\Storage::fake('exports');
    \App\Models\User::factory()->withRole('Super Admin')->create();
    \Illuminate\Support\Facades\Storage::disk('private')->put('snapshots/demo.jpg', 'jpeg');
    $export = app(\App\Services\Backup\ExportAllService::class)->run('test-client-key');
    expect($export['archive_path'])->not->toBeEmpty();
});
