<?php

namespace App\Console\Commands;

use App\Support\EdgeDeviceCredentials;
use App\Support\StandbyPoleIngest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Walkthrough stand-in for poles 1–4: same IR4_BASE_URL + /api/ingest/* + heartbeats as EdgeCompute.
 *
 * Device letters: t=tick/online, g=gas, r=rfid, h=helmet, v=vest.
 */
final class StandbyPolesCommand extends Command
{
    private const LOOP_SECONDS = 30;

    private const ALARM_HOLD_SECONDS = 45;

    protected $signature = 'ir4:s
                            {action? : t|g|r|h|v (tick, gas, rfid, helmet, vest)}
                            {pole? : pole 1-4}
                            {tag? : rfid tag number or EPC (default 1)}
                            {--alarm : With g: post above warn thresholds}
                            {--loop : Repeat t (all poles) or g (one pole) every 30s}
                            {--url= : Device API base (default IR4_BASE_URL → APP_URL)}';

    /** @var list<string> */
    protected $aliases = ['ir4:standby'];

    protected $description = 'Stand in for poles 1–4 via the same device ingest APIs as EdgeCompute';

    public function handle(): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        if ($action === '' || $action === 'help' || $action === '?') {
            $this->printUsage();

            return self::SUCCESS;
        }

        [$base, $source] = $this->resolveBaseUrl();
        $client = new StandbyPoleIngest($base);
        $this->line("IR4 device API → {$client->baseUrl()} ({$source})");

        try {
            return match ($action) {
                't', 'tick', 'online' => $this->runOnline($client),
                'g', 'gas' => $this->runGas($client),
                'r', 'rfid', 'a', 'at', 'arrive' => $this->runRfid($client),
                'h', 'helmet' => $this->runPpe($client, 'missing_helmet'),
                'v', 'vest' => $this->runPpe($client, 'missing_vest'),
                default => $this->failAction($action),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function printUsage(): void
    {
        $this->line('Standby = fake poles 1–4 calling the same device APIs as EdgeCompute.');
        $this->line('Base URL: --url → IR4_STANDBY_URL → IR4_BASE_URL → APP_URL');
        $this->newLine();
        $this->table(
            ['Command', 'Device', 'What it does'],
            [
                ['ir4:s t --loop', 'tick', 'Ambient gas + heartbeats poles 1–4 every 30s'],
                ['ir4:s g {pole}', 'gas', 'One ambient gas reading'],
                ['ir4:s g {pole} --loop', 'gas', 'Normal gas readings every 30s for that pole'],
                ['ir4:s g {pole} --alarm', 'gas', 'One reading above warn thresholds'],
                ['ir4:s g {pole} --alarm --loop', 'gas', 'Alarm readings every 30s for that pole'],
                ['ir4:s r {pole} [tag]', 'rfid', 'POST /api/ingest/tag-readings (default tag 1)'],
                ['ir4:s h {pole}', 'helmet', 'POST /api/ingest/ppe-violations missing_helmet'],
                ['ir4:s v {pole}', 'vest', 'POST /api/ingest/ppe-violations missing_vest'],
            ],
        );
        $this->line('Long names also work: online, gas, rfid, helmet, vest (and a=rfid).');
        $this->line('Expect ingest http=202. Do not point at a Flutter :9100 process.');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveBaseUrl(): array
    {
        $fromOption = trim((string) $this->option('url'));
        if ($fromOption !== '') {
            return [rtrim($fromOption, '/'), '--url'];
        }

        $standby = trim((string) env('IR4_STANDBY_URL', ''));
        if ($standby !== '') {
            return [rtrim($standby, '/'), 'IR4_STANDBY_URL'];
        }

        $edge = trim((string) env('IR4_BASE_URL', ''));
        if ($edge !== '') {
            return [rtrim($edge, '/'), 'IR4_BASE_URL'];
        }

        return [rtrim((string) config('app.url'), '/'), 'APP_URL'];
    }

    private function failAction(string $action): int
    {
        $this->error("Unknown [{$action}]. Run: php artisan ir4:s help");

        return self::FAILURE;
    }

    private function runOnline(StandbyPoleIngest $client): int
    {
        $once = function () use ($client): void {
            foreach ([1, 2, 3, 4] as $pole) {
                if ($this->isPoleGasOwned($pole)) {
                    $this->line("pole-0{$pole} gas skipped (per-pole gas loop owns it)");
                } else {
                    $this->postGas($client, $pole, alarm: false);
                }
                $this->heartbeatPole($client, $pole);
            }
            $this->line('online poles 1–4 (ambient gas + heartbeats)');
        };

        $once();
        if (! $this->option('loop')) {
            return self::SUCCESS;
        }

        $this->warn('online loop '.self::LOOP_SECONDS.'s. Ctrl-C to stop.');
        while (true) {
            sleep(self::LOOP_SECONDS);
            $once();
        }
    }

    private function runGas(StandbyPoleIngest $client): int
    {
        $pole = $this->pole((string) $this->argument('pole'));
        $alarm = (bool) $this->option('alarm');
        $once = function () use ($client, $pole, $alarm): void {
            $result = $this->postGas($client, $pole, alarm: $alarm);
            if ($alarm) {
                $this->holdAlarm($pole);
            } elseif ($this->option('loop')) {
                $this->holdAmbientLoop($pole);
            }
            $mode = $alarm ? 'alarm' : 'ambient';
            $this->info("gas {$mode} DEV-GAS-0{$pole} http={$result['status']}");
        };

        $once();
        if (! $this->option('loop')) {
            return self::SUCCESS;
        }

        $label = $alarm ? 'alarm' : 'ambient';
        $this->warn("gas {$label} loop ".self::LOOP_SECONDS."s on pole-0{$pole}. Ctrl-C to stop.");
        while (true) {
            sleep(self::LOOP_SECONDS);
            $once();
        }
    }

    private function runRfid(StandbyPoleIngest $client): int
    {
        $rawPole = strtolower(trim((string) $this->argument('pole')));
        if ($rawPole === '' || $rawPole === 'g' || $rawPole === 'gate') {
            $this->error('Usage: ir4:s r {1-4} [tag]  (poles only; no gate)');

            return self::FAILURE;
        }

        $pole = $this->pole($rawPole);
        $tag = $this->expandTag((string) ($this->argument('tag') ?: '1'));
        $ref = 'DEV-RFID-0'.$pole;
        $cred = $this->cred($ref);
        $rssi = round(-52 - (mt_rand(0, 160) / 10), 1);
        $result = $client->postTagReadings($cred['token'], [[
            'event_uid' => (string) Str::uuid(),
            'reader_ref' => $ref,
            'tag_uid' => $tag,
            'recorded_at' => now()->toIso8601String(),
            'rssi' => $rssi,
            'antenna' => mt_rand(1, 2),
        ]]);
        $this->info("rfid {$ref} tag={$tag} rssi={$rssi} http={$result['status']}");

        return self::SUCCESS;
    }

    private function runPpe(StandbyPoleIngest $client, string $type): int
    {
        $pole = $this->pole((string) $this->argument('pole'));
        $ref = 'DEV-CAM-FIXED-0'.$pole;
        $camera = 'CAM-FIXED-0'.$pole;
        $cred = $this->cred($ref);
        $result = $client->postPpeViolations($cred['token'], [[
            'event_uid' => (string) Str::uuid(),
            'camera_ref' => $camera,
            'event_type' => $type,
            'detected_at' => now()->toIso8601String(),
            'confidence' => round(0.82 + (mt_rand(0, 12) / 100), 2),
            'worker_count' => 1,
        ]]);
        $this->info("ppe {$type} {$camera} http={$result['status']}");

        return self::SUCCESS;
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function postGas(StandbyPoleIngest $client, int $pole, bool $alarm): array
    {
        $ref = 'DEV-GAS-0'.$pole;
        $cred = $this->cred($ref);
        $jitter = static fn (float $base, float $span): float => round($base + ((mt_rand(0, 1000) / 1000) * $span), 2);
        // GasThresholdSeeder: LEL warn 10, H2S 5, CO 25, CO2 5000.
        $fields = $alarm
            ? [
                'lel_pct' => $jitter(12.0, 4.0),
                'h2s_ppm' => $jitter(6.5, 2.5),
                'o2_pct' => $jitter(20.6, 0.2),
                'co_ppm' => $jitter(30.0, 10.0),
                'co2_ppm' => $jitter(5600, 900),
            ]
            : [
                'lel_pct' => $jitter(0.0, 0.15),
                'h2s_ppm' => $jitter(0.0, 0.4),
                'o2_pct' => $jitter(20.75, 0.2),
                'co_ppm' => $jitter(0.4, 1.6),
                'co2_ppm' => $jitter(410, 70),
            ];

        return $client->postGasReadings($cred['token'], [array_merge([
            'event_uid' => (string) Str::uuid(),
            'device_ref' => $ref,
            'recorded_at' => now()->toIso8601String(),
        ], $fields)]);
    }

    private function heartbeatPole(StandbyPoleIngest $client, int $pole): void
    {
        foreach (['DEV-GAS-0', 'DEV-RFID-0', 'DEV-CAM-FIXED-0', 'DEV-CAM-PTZ-0'] as $prefix) {
            $cred = $this->cred($prefix.$pole);
            $client->postHeartbeat($cred['uuid'], $cred['token']);
        }
    }

    private function alarmCacheKey(int $pole): string
    {
        return "ir4:standby:gas-alarm:{$pole}";
    }

    private function ambientLoopCacheKey(int $pole): string
    {
        return "ir4:standby:gas-ambient:{$pole}";
    }

    private function holdAlarm(int $pole): void
    {
        Cache::forget($this->ambientLoopCacheKey($pole));
        Cache::put($this->alarmCacheKey($pole), true, now()->addSeconds(self::ALARM_HOLD_SECONDS));
    }

    private function holdAmbientLoop(int $pole): void
    {
        Cache::forget($this->alarmCacheKey($pole));
        Cache::put($this->ambientLoopCacheKey($pole), true, now()->addSeconds(self::ALARM_HOLD_SECONDS));
    }

    private function isPoleGasOwned(int $pole): bool
    {
        return Cache::has($this->alarmCacheKey($pole)) || Cache::has($this->ambientLoopCacheKey($pole));
    }

    /**
     * @return array{ref: string, uuid: string, token: string, type: string, notes: string}
     */
    private function cred(string $ref): array
    {
        $row = EdgeDeviceCredentials::find($ref);
        if ($row === null) {
            throw new RuntimeException("No credentials for {$ref}");
        }

        return $row;
    }

    private function pole(string $raw): int
    {
        $pole = (int) preg_replace('/\D+/', '', $raw);
        if ($pole < 1 || $pole > 4) {
            throw new RuntimeException('Pole must be 1–4.');
        }

        return $pole;
    }

    private function expandTag(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, 'E280')) {
            return $raw;
        }
        if (preg_match('/^(IR4)?W?(\d{1,4})$/', $raw, $m) === 1) {
            return sprintf('E280116060000203IR4W%04d', (int) $m[2]);
        }

        return $raw;
    }
}
