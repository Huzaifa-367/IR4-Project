<?php

use App\Models\User;
use App\Support\PermissionCatalogue;

beforeEach(function () {
    $this->withoutVite();

    $this->root = sys_get_temp_dir().'/ir4-rec-http-'.uniqid('', true);
    mkdir($this->root.'/pole1/cam1', 0755, true);
    file_put_contents($this->root.'/pole1/cam1/hour.mp4', str_repeat('x', 64));

    config([
        'recordings.root' => $this->root,
        'recordings.use_x_accel' => true,
        'recordings.x_accel_prefix' => '/internal-recordings/',
        'recordings.allowed_extensions' => ['mp4', 'm4v', 'webm', 'mkv', 'mov', 'ts'],
    ]);
});

afterEach(function () {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->root);
});

it('forbids recordings without permission', function () {
    $user = User::factory()->withRole('Client Representative')->create();

    $this->actingAs($user)
        ->get(route('recordings.index'))
        ->assertForbidden();
});

it('browses the archive root for operators with view-recordings', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($user)
        ->get(route('recordings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recordings/index')
            ->where('browse.root_readable', true)
            ->has('browse.entries', 1)
            ->where('browse.entries.0.name', 'pole1'));
});

it('streams with X-Accel-Redirect when enabled', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($user)
        ->get(route('recordings.stream', ['path' => 'pole1/cam1/hour.mp4']))
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/internal-recordings/pole1/cam1/hour.mp4')
        ->assertHeader('Content-Type', 'video/mp4');
});

it('falls back to file response when X-Accel is disabled', function () {
    config(['recordings.use_x_accel' => false]);

    $user = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($user)
        ->get(route('recordings.stream', ['path' => 'pole1/cam1/hour.mp4']))
        ->assertOk()
        ->assertHeaderMissing('X-Accel-Redirect');
});

it('rejects traversal on stream', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($user)
        ->get(route('recordings.stream', ['path' => '../hour.mp4']))
        ->assertStatus(400);
});

it('includes view-recordings in the catalogue', function () {
    expect(PermissionCatalogue::all())->toContain('view-recordings');
});
