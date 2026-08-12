#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build an obfuscated (base64+gzip) Server tree under repo-root Release/server.
 *
 * Frontend: ships Vite public/build only — no resources/js or resources/css source.
 * PHP app/: gzip+base64 wrappers. Weak at-rest obfuscation, not strong IP protection.
 *
 * Usage (from repo root):
 *   php Server/scripts/build-encoded-release.php
 * Or from Server/:
 *   php scripts/build-encoded-release.php
 */

$serverRoot = dirname(__DIR__);
$repoRoot = dirname($serverRoot);
$releaseRoot = $repoRoot.'/Release';
$outRoot = $releaseRoot.'/server';

if (! is_dir($serverRoot.'/app')) {
    fwrite(STDERR, "ERROR: Server/app not found at {$serverRoot}\n");
    exit(1);
}

echo "==> Ensuring Vite production build (public/build)\n";
$manifest = $serverRoot.'/public/build/manifest.json';
if (! is_file($manifest)) {
    echo "    manifest missing — running npm ci && npm run build in Server/\n";
    $npmCi = runCommand('npm ci --no-audit --no-fund', $serverRoot);
    if ($npmCi !== 0) {
        fwrite(STDERR, "ERROR: npm ci failed\n");
        exit(1);
    }
    $npmBuild = runCommand('npm run build', $serverRoot);
    if ($npmBuild !== 0) {
        fwrite(STDERR, "ERROR: npm run build failed\n");
        exit(1);
    }
}
if (! is_file($manifest)) {
    fwrite(STDERR, "ERROR: {$manifest} still missing after build.\n");
    exit(1);
}
echo "    using {$manifest}\n";

echo "==> Cleaning {$outRoot}\n";
if (is_dir($outRoot)) {
    removeTree($outRoot);
}
mkdir($outRoot, 0755, true);

$copyPaths = [
    'artisan',
    'composer.json',
    'composer.lock',
    'bootstrap',
    'config',
    'database',
    'public',
    'routes',
    '.htaccess',
];

echo "==> Copying Server runtime tree (plain; no frontend source)\n";
foreach ($copyPaths as $rel) {
    $src = $serverRoot.'/'.$rel;
    if (! file_exists($src)) {
        continue;
    }
    $dst = $outRoot.'/'.$rel;
    if (is_dir($src)) {
        mirrorDir($src, $dst, shouldSkipCopy(...));
    } else {
        $dir = dirname($dst);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($src, $dst);
    }
}

echo "==> Copying resources/views only (Blade; no js/css/tsx)\n";
$viewsSrc = $serverRoot.'/resources/views';
if (! is_dir($viewsSrc)) {
    fwrite(STDERR, "ERROR: resources/views missing\n");
    exit(1);
}
mirrorDir($viewsSrc, $outRoot.'/resources/views', shouldSkipCopy(...));

if (! is_file($outRoot.'/public/build/manifest.json')) {
    fwrite(STDERR, "ERROR: public/build was not copied into the release.\n");
    exit(1);
}

echo "==> Creating empty storage skeleton\n";
foreach ([
    'storage/app/public',
    'storage/app/private',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
] as $dir) {
    $path = $outRoot.'/'.$dir;
    if (! is_dir($path)) {
        mkdir($path, 0755, true);
    }
    file_put_contents($path.'/.gitignore', "*\n!.gitignore\n");
}

echo "==> Encoding Server/app/**/*.php → Release/server/app\n";
$encoded = 0;
$appSrc = $serverRoot.'/app';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appSrc, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($appSrc) + 1);
    $dst = $outRoot.'/app/'.$rel;
    $dir = dirname($dst);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dst, encodePhpFile($file->getPathname()));
    $encoded++;
}
echo "    encoded {$encoded} PHP files\n";

echo "==> Writing Release metadata\n";
$readme = <<<'MD'
# IR4 encoded server release

Built by `php Server/scripts/build-encoded-release.php`.

## What this is

- `server/app/**/*.php` — gzip + base64 + `eval` (weak at-rest obfuscation).
- **Frontend:** only Vite `public/build/` (compiled JS/CSS). No `resources/js` or `resources/css`.
- `resources/views` — Blade only (required by Laravel/Inertia).
- Not strong encryption; compiled JS can still be beautified in a browser.

## Deploy on SCC

```bash
cd Release/server
composer install --no-dev --optimize-autoloader --no-interaction
# link shared .env — never ship secrets in this tree
php artisan optimize:clear
# no npm on the SCC — assets are already in public/build
```

Verify integrity:

```bash
cd Release && sha256sum -c MANIFEST.sha256
```

Rebuild (from repo root; runs `npm run build` if `public/build` is missing):

```bash
cd Server && npm ci && npm run build && cd ..
php Server/scripts/build-encoded-release.php
```
MD;
file_put_contents($releaseRoot.'/README.md', $readme);

$manifestLines = [];
$manifestIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($outRoot, FilesystemIterator::SKIP_DOTS)
);
/** @var SplFileInfo $file */
foreach ($manifestIterator as $file) {
    if (! $file->isFile()) {
        continue;
    }
    $rel = 'server/'.substr($file->getPathname(), strlen($outRoot) + 1);
    $rel = str_replace('\\', '/', $rel);
    $hash = hash_file('sha256', $file->getPathname());
    $manifestLines[] = $hash.'  '.$rel;
}
sort($manifestLines);
file_put_contents($releaseRoot.'/MANIFEST.sha256', implode("\n", $manifestLines)."\n");

echo "==> Done\n";
echo "    Output: {$outRoot}\n";
echo "    Manifest: {$releaseRoot}/MANIFEST.sha256\n";
assertNoFrontendSource($outRoot);

function encodePhpFile(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;
    $source = preg_replace('/^<\?php\s*/', '', $source, 1) ?? $source;

    $blob = base64_encode(gzencode($source, 9));
    if ($blob === false) {
        throw new RuntimeException("Cannot encode {$path}");
    }

    // Minimal stub: no labels, no paths, no IR4 hints. Integrity still checked.
    return <<<PHP
<?php
\$a=base64_decode('{$blob}',true);if(\$a===false)exit(1);
\$a=gzdecode(\$a);if(\$a===false)exit(1);
eval(\$a);

PHP;
}

function shouldSkipCopy(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    $skipParts = [
        '/node_modules/',
        '/vendor/',
        '/.git/',
        '/public/hot',
        '/public/storage',
        '/resources/js/',
        '/resources/css/',
        '/storage/logs/',
        '/storage/framework/cache/data/',
        '/storage/framework/sessions/',
        '/storage/framework/views/',
        '/storage/pail/',
        '/bootstrap/cache/',
    ];
    foreach ($skipParts as $part) {
        if (str_contains($normalized, $part)) {
            return true;
        }
    }
    $base = basename($normalized);
    if (in_array($base, ['.env', '.env.backup', 'auto.crt', 'auto.key', '.DS_Store'], true)) {
        return true;
    }
    // Do not ship source maps (can reverse toward original TS/JS).
    if (str_ends_with($base, '.map')) {
        return true;
    }
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if (in_array($ext, ['ts', 'tsx', 'jsx', 'vue', 'svelte'], true)) {
        return true;
    }

    return false;
}

function assertNoFrontendSource(string $outRoot): void
{
    foreach (['resources/js', 'resources/css'] as $forbidden) {
        $path = $outRoot.'/'.$forbidden;
        if (is_dir($path)) {
            fwrite(STDERR, "ERROR: frontend source leaked into release: {$path}\n");
            exit(1);
        }
    }
    $hit = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($outRoot, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (in_array($ext, ['ts', 'tsx', 'jsx'], true)) {
            $hit[] = $file->getPathname();
        }
    }
    if ($hit !== []) {
        fwrite(STDERR, "ERROR: TS/JSX source found in release:\n".implode("\n", array_slice($hit, 0, 20))."\n");
        exit(1);
    }
    echo "    frontend source check: OK (views + public/build only)\n";
}

function runCommand(string $command, string $cwd): int
{
    $descriptor = [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ];
    $proc = proc_open($command, $descriptor, $pipes, $cwd);
    if (! is_resource($proc)) {
        return 1;
    }

    return proc_close($proc);
}

function mirrorDir(string $src, string $dst, callable $skip): void
{
    if (! is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if ($skip($path)) {
            continue;
        }
        $rel = substr($path, strlen($src) + 1);
        $target = $dst.'/'.$rel;
        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }
            continue;
        }
        $parent = dirname($target);
        if (! is_dir($parent)) {
            mkdir($parent, 0755, true);
        }
        copy($path, $target);
    }
}

function removeTree(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}
