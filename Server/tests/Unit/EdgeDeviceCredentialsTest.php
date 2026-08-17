<?php

/**
 * @return list<array{ref: string, uuid: string, token: string}>
 */
function parseCredentialsMarkdown(string $path): array
{
    $rows = [];
    foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
        if (! preg_match(
            '/^\|\s*(rfid|gas|cam_ai)\s*\|\s*(DEV-[A-Z0-9-]+)\s*\|\s*([0-9a-f-]{36})\s*\|\s*([A-Za-z0-9]+)\s*\|/i',
            $line,
            $match,
        )) {
            continue;
        }
        $rows[] = [
            'ref' => $match[2],
            'uuid' => strtolower($match[3]),
            'token' => $match[4],
        ];
    }

    return $rows;
}

it('keeps default device credentials in sync with EdgeCompute/credentials.md', function () {
    $php = require dirname(__DIR__, 2).'/database/data/device_credentials.php';
    $md = parseCredentialsMarkdown(dirname(__DIR__, 3).'/EdgeCompute/credentials.md');

    $fromPhp = array_map(static fn (array $row): array => [
        'ref' => $row['ref'],
        'uuid' => $row['uuid'],
        'token' => $row['token'],
    ], $php);

    expect($fromPhp)->toHaveCount(33)
        ->and($fromPhp)->toEqual($md);
});
