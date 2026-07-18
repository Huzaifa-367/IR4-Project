<?php

use App\Models\User;
use App\Services\Backup\ExportAllService;
use App\Services\Backup\ExportManifestService;
use App\Services\Backup\SecureWipeService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\SettingsSeeder::class);
    Storage::fake('private');
    Storage::fake('backups');
    Storage::fake('exports');
});

// DOC-21 scenario 12a: export marker and refusal paths.
it('scenario 12: export-all refuses wipe without verified export', function () {
    User::factory()->withRole('Super Admin')->create();
    Storage::disk('private')->put('snapshots/scenario-12.jpg', 'jpeg-bytes');

    $export = app(ExportAllService::class)->run('scenario-12-key');
    expect(Storage::disk('exports')->exists($export['archive_path']))->toBeTrue();

    $marker = app(ExportManifestService::class)->latest();
    expect($marker)->not->toBeNull()
        ->and($marker['archive_sha256'] ?? null)->not->toBeEmpty();

    $wipe = app(SecureWipeService::class);

    expect(fn () => $wipe->wipe('wrong-phrase'))
        ->toThrow(RuntimeException::class);

    expect(fn () => $wipe->wipe(SecureWipeService::CONFIRM_PHRASE, 'nonexistent-export-id'))
        ->toThrow(RuntimeException::class);

    Storage::disk('exports')->deleteDirectory('final/markers');
    expect(fn () => $wipe->wipe(SecureWipeService::CONFIRM_PHRASE))
        ->toThrow(RuntimeException::class);
});

// DOC-21 scenario 12b: verified export then secure wipe with receipt marker.
it('scenario 12: secure wipe succeeds after verified export and writes receipt', function () {
    User::factory()->withRole('Super Admin')->create();
    Storage::disk('private')->put('snapshots/demo.jpg', 'jpeg');

    $export = app(ExportAllService::class)->run('test-client-key');
    expect(Storage::disk('exports')->exists($export['archive_path']))->toBeTrue();

    $result = app(SecureWipeService::class)->wipe(
        SecureWipeService::CONFIRM_PHRASE,
        $export['export_id'],
    );

    expect(Storage::disk('exports')->exists($result['receipt']))->toBeTrue()
        ->and(Storage::disk('private')->exists('snapshots/demo.jpg'))->toBeFalse()
        ->and(Storage::disk('exports')->exists($export['archive_path']))->toBeTrue();
});
