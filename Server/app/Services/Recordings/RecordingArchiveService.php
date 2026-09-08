<?php

namespace App\Services\Recordings;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Path-jailed browser over RECORDINGS_ROOT (DOC-24).
 *
 * Never returns absolute host paths to the browser — only relative keys.
 */
final class RecordingArchiveService
{
    /**
     * @return array{
     *     root_exists: bool,
     *     root_readable: bool,
     *     relative: string,
     *     breadcrumbs: list<array{name: string, path: string}>,
     *     entries: list<array{
     *         name: string,
     *         path: string,
     *         type: 'dir'|'file',
     *         size: int|null,
     *         mtime: string|null,
     *         is_playable: bool
     *     }>,
     *     truncated: bool,
     *     top_level_count: int|null
     * }
     */
    public function browse(string $relative = ''): array
    {
        $relative = $this->normalizeRelative($relative);
        $root = $this->rootRealPath();

        $rootExists = $root !== null && is_dir($root);
        $rootReadable = $rootExists && is_readable($root);

        $topLevelCount = null;
        if ($rootReadable) {
            $topLevelCount = count(array_filter(
                scandir($root) ?: [],
                static fn (string $name): bool => $name !== '.' && $name !== '..',
            ));
        }

        if (! $rootReadable) {
            return [
                'root_exists' => $rootExists,
                'root_readable' => false,
                'relative' => $relative,
                'breadcrumbs' => $this->breadcrumbs($relative),
                'entries' => [],
                'truncated' => false,
                'top_level_count' => $topLevelCount,
            ];
        }

        $absolute = $this->resolveExisting($relative, allowDirectory: true, allowFile: false);

        $entries = [];
        $truncated = false;
        $max = max(1, (int) config('recordings.max_list_entries', 2000));
        $names = scandir($absolute) ?: [];
        natcasesort($names);

        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            // Incomplete MediaMTX writes / editor junk (e.g. .15-26-23.mp4.XXXX).
            if (str_starts_with($name, '.')) {
                continue;
            }
            if (count($entries) >= $max) {
                $truncated = true;
                break;
            }

            $childAbs = $absolute.DIRECTORY_SEPARATOR.$name;
            // Skip anything that escapes via symlink.
            if (! $this->isInsideRoot($childAbs)) {
                continue;
            }

            $childRel = $relative === '' ? $name : $relative.'/'.$name;
            $isDir = is_dir($childAbs);
            $size = $isDir ? null : (is_file($childAbs) ? (int) filesize($childAbs) : null);
            $mtime = filemtime($childAbs);
            $entries[] = [
                'name' => $name,
                'path' => $childRel,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $size,
                'mtime' => $mtime ? Carbon::createFromTimestamp($mtime)->toIso8601String() : null,
                'is_playable' => ! $isDir && $this->isPlayableName($name),
            ];
        }

        // Directories first, then files; natural name order within type.
        usort($entries, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }

            return strnatcasecmp($a['name'], $b['name']);
        });

        return [
            'root_exists' => true,
            'root_readable' => true,
            'relative' => $relative,
            'breadcrumbs' => $this->breadcrumbs($relative),
            'entries' => $entries,
            'truncated' => $truncated,
            'top_level_count' => $topLevelCount,
        ];
    }

    /**
     * @return array{absolute: string, relative: string, mime: string, size: int, name: string}
     */
    public function assertPlayableFile(string $relative): array
    {
        $relative = $this->normalizeRelative($relative);
        if ($relative === '' || ! $this->isPlayableName(basename($relative))) {
            throw new HttpException(404, 'Recording not found or not a playable video.');
        }

        $absolute = $this->resolveExisting($relative, allowDirectory: false, allowFile: true);
        if (! is_readable($absolute)) {
            throw new HttpException(404, 'Recording not readable.');
        }

        $detected = mime_content_type($absolute) ?: 'application/octet-stream';
        $mime = str_starts_with($detected, 'video/')
            ? $detected
            : $this->mimeForExtension(pathinfo($relative, PATHINFO_EXTENSION));

        return [
            'absolute' => $absolute,
            'relative' => $relative,
            'mime' => $mime,
            'size' => (int) filesize($absolute),
            'name' => basename($relative),
        ];
    }

    public function xAccelPath(string $relative): string
    {
        $relative = $this->normalizeRelative($relative);
        $prefix = rtrim((string) config('recordings.x_accel_prefix', '/internal-recordings/'), '/').'/';

        return $prefix.str_replace('\\', '/', $relative);
    }

    public function usesXAccel(): bool
    {
        return (bool) config('recordings.use_x_accel', true);
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function breadcrumbs(string $relative): array
    {
        $crumbs = [['name' => 'Recordings', 'path' => '']];
        if ($relative === '') {
            return $crumbs;
        }

        $accum = [];
        foreach (explode('/', $relative) as $segment) {
            $accum[] = $segment;
            $crumbs[] = [
                'name' => $segment,
                'path' => implode('/', $accum),
            ];
        }

        return $crumbs;
    }

    private function normalizeRelative(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = rawurldecode($relative);
        if (str_contains($relative, "\0")) {
            throw new HttpException(400, 'Invalid path.');
        }
        if ($relative !== '' && ($relative[0] === '/' || preg_match('#^[A-Za-z]:/#', $relative) === 1)) {
            throw new HttpException(400, 'Absolute paths are not allowed.');
        }

        $parts = [];
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new HttpException(400, 'Path traversal is not allowed.');
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function rootRealPath(): ?string
    {
        $root = (string) config('recordings.root', '/data2/video');
        if ($root === '') {
            return null;
        }
        $real = realpath($root);

        return $real !== false ? $real : null;
    }

    private function resolveExisting(string $relative, bool $allowDirectory, bool $allowFile): string
    {
        $root = $this->rootRealPath();
        if ($root === null) {
            throw new HttpException(503, 'Recordings root is not available.');
        }

        $candidate = $relative === ''
            ? $root
            : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $real = realpath($candidate);
        if ($real === false || ! $this->isInsideRoot($real, $root)) {
            throw new HttpException(404, 'Path not found.');
        }

        if (is_dir($real) && ! $allowDirectory) {
            throw new HttpException(404, 'Expected a file.');
        }
        if (is_file($real) && ! $allowFile) {
            throw new HttpException(404, 'Expected a directory.');
        }
        if (! is_dir($real) && ! is_file($real)) {
            throw new HttpException(404, 'Path not found.');
        }

        return $real;
    }

    private function isInsideRoot(string $absolute, ?string $root = null): bool
    {
        $root ??= $this->rootRealPath();
        if ($root === null) {
            return false;
        }

        $real = realpath($absolute);
        if ($real === false) {
            // For list(): check parent of dangling? We only call on existing scandir entries.
            return false;
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $realNorm = rtrim($real, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return $real === rtrim($root, DIRECTORY_SEPARATOR)
            || str_starts_with($realNorm, $root);
    }

    private function isPlayableName(string $name): bool
    {
        if (str_starts_with($name, '.')) {
            return false;
        }
        // Incomplete MediaMTX temp: name.mp4.XXXXXX
        if (preg_match('/\.(mp4|m4v|webm|mkv|mov|ts)\.[A-Za-z0-9]{4,}$/i', $name) === 1) {
            return false;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        /** @var list<string> $allowed */
        $allowed = config('recordings.allowed_extensions', []);

        return $ext !== '' && in_array($ext, $allowed, true);
    }

    private function mimeForExtension(string $ext): string
    {
        return match (strtolower($ext)) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'mov' => 'video/quicktime',
            'ts' => 'video/mp2t',
            default => 'application/octet-stream',
        };
    }
}
