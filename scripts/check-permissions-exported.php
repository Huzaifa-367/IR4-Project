<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$expected = App\Support\PermissionCatalogue::all();
$ts = file_get_contents(__DIR__.'/../resources/js/types/enums.ts');
$md = file_get_contents(__DIR__.'/../PERMISSIONS.md');

if ($ts === false || $md === false) {
    fwrite(STDERR, "PERMISSIONS.md or enums.ts missing\n");
    exit(1);
}

$missingTs = [];
$missingMd = [];

foreach ($expected as $perm) {
    if (! str_contains($ts, "'{$perm}'")) {
        $missingTs[] = $perm;
    }
    if (! str_contains($md, "`{$perm}`")) {
        $missingMd[] = $perm;
    }
}

if ($missingTs !== [] || $missingMd !== []) {
    if ($missingTs !== []) {
        fwrite(STDERR, "Missing from enums.ts Permission union:\n- ".implode("\n- ", $missingTs)."\n");
    }
    if ($missingMd !== []) {
        fwrite(STDERR, "Missing from PERMISSIONS.md:\n- ".implode("\n- ", $missingMd)."\n");
    }
    exit(1);
}

echo "PERMISSIONS.md / Permission union check passed.\n";
