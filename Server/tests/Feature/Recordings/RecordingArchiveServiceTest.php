<?php

use App\Services\Recordings\RecordingArchiveService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/ir4-recordings-'.uniqid('', true);
    mkdir($this->root.'/pole1/cam1/2026-09-09', 0755, true);
    file_put_contents($this->root.'/pole1/cam1/2026-09-09/12.mp4', 'fake-video');
    file_put_contents($this->root.'/pole1/cam1/2026-09-09/notes.txt', 'nope');
    mkdir($this->root.'/pole2', 0755, true);

    config([
        'recordings.root' => $this->root,
        'recordings.use_x_accel' => true,
        'recordings.x_accel_prefix' => '/internal-recordings/',
        'recordings.allowed_extensions' => ['mp4', 'm4v', 'webm', 'mkv', 'mov', 'ts'],
        'recordings.max_list_entries' => 2000,
    ]);

    $this->archive = app(RecordingArchiveService::class);
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

it('lists root poles as directories', function () {
    $page = $this->archive->browse('');

    expect($page['root_readable'])->toBeTrue()
        ->and(collect($page['entries'])->pluck('name')->all())->toContain('pole1', 'pole2')
        ->and(collect($page['entries'])->every(fn ($e) => $e['type'] === 'dir'))->toBeTrue();
});

it('lists date folder files and marks playable extensions', function () {
    $page = $this->archive->browse('pole1/cam1/2026-09-09');

    $byName = collect($page['entries'])->keyBy('name');
    expect($byName['12.mp4']['is_playable'])->toBeTrue()
        ->and($byName['12.mp4']['type'])->toBe('file')
        ->and($byName['notes.txt']['is_playable'])->toBeFalse();
});

it('rejects path traversal and absolute paths', function () {
    expect(fn () => $this->archive->browse('../etc'))->toThrow(HttpException::class);
    expect(fn () => $this->archive->browse('/etc/passwd'))->toThrow(HttpException::class);
    expect(fn () => $this->archive->assertPlayableFile('pole1/../../etc/passwd'))->toThrow(HttpException::class);
});

it('rejects symlink escape outside root', function () {
    $outside = sys_get_temp_dir().'/ir4-outside-'.uniqid('', true);
    mkdir($outside);
    file_put_contents($outside.'/secret.mp4', 'secret');
    symlink($outside, $this->root.'/escape');

    expect(fn () => $this->archive->assertPlayableFile('escape/secret.mp4'))
        ->toThrow(HttpException::class);

    unlink($this->root.'/escape');
    unlink($outside.'/secret.mp4');
    rmdir($outside);
});

it('resolves playable files and builds x-accel path', function () {
    $file = $this->archive->assertPlayableFile('pole1/cam1/2026-09-09/12.mp4');

    expect($file['relative'])->toBe('pole1/cam1/2026-09-09/12.mp4')
        ->and($file['size'])->toBeGreaterThan(0)
        ->and($this->archive->xAccelPath($file['relative']))
        ->toBe('/internal-recordings/pole1/cam1/2026-09-09/12.mp4');
});

it('hides incomplete temp files from browse listings', function () {
    file_put_contents($this->root.'/pole1/cam1/2026-09-09/.partial.mp4.ABC123', 'temp');

    $page = $this->archive->browse('pole1/cam1/2026-09-09');
    $names = collect($page['entries'])->pluck('name')->all();

    expect($names)->toContain('12.mp4')
        ->and($names)->not->toContain('.partial.mp4.ABC123');
});

it('rejects non-playable extensions for stream', function () {
    expect(fn () => $this->archive->assertPlayableFile('pole1/cam1/2026-09-09/notes.txt'))
        ->toThrow(HttpException::class);
});
