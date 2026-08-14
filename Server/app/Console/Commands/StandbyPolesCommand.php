<?php

namespace App\Console\Commands;

use App\Support\EdgeDeviceCredentials;
use App\Support\StandbyPoleIngest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * SCC2 walkthrough ingest: real /api/ingest/* tokens, one-shot RFID/PPE, ambient gas ticks.
 *
 * Gas must tick (detectors go stale otherwise). RFID/PPE are never looped.
 * RFID is always commanded — field readers may be dead even with a person on site.
 */
final class StandbyPolesCommand extends Command
{
    protected $signature = 'ir4:s
                            {action : t=tick, r=rfid, a=arrive pole, h=helmet, v=vest, g=gas bump}
                            {a? : pole 1-4}
                            {b? : tag number (rfid only, default 1)}
                            {--loop : Repeat t every 30s}
                            {--url= : Ingest base URL (default http://127.0.0.1:9100)}';

    /** @var list<string> */
    protected $aliases = ['ir4:standby'];

    protected $description = 'SCC2 walkthrough ingest: t keep-alive, r/h/v/g one-shot';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));
        $base = (string) ($this->option('url') ?: env('IR4_STANDBY_URL', 'http://127.0.0.1:9100'));
        $client = new StandbyPoleIngest($base);

        try {
            return match ($action) {
                't', 'tick' => $this->runTick($client),
                'r', 'rfid' => $this->runRfid($client),
                'a', 'at' => $this->runArrive($client),
                'h', 'helmet' => $this->runPpe($client, 'missing_helmet'),
                'v', 'vest' => $this->runPpe($client, 'missing_vest'),
                'g', 'gas' => $this->runGasBump($client),
                default => $this->failAction($action),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function failAction(string $action): int
    {
        $this->error("Unknown [{$action}]. Use: t | a {pole} [tag] | r {pole} [tag] | h {pole} | v {pole} | g {pole}");

        return self::FAILURE;
    }

    private function runTick(StandbyPoleIngest $client): int
    {
        $once = function () use ($client): void {
            foreach ([1, 2, 3, 4] as $pole) {
                $this->postAmbientGas($client, $pole);
                $this->heartbeatPole($client, $pole);
            }
            $this->line('tick poles 1–4 (ambient gas + heartbeats)');
        };

        $once();
        if (! $this->option('loop')) {
            return self::SUCCESS;
        }

        $this->warn('Keep-alive 30s. Ctrl-C to stop.');
        while (true) {
            sleep(30);
            $once();
        }
    }

    private function runRfid(StandbyPoleIngest $client): int
    {
        $target = strtolower((string) $this->argument('a'));
        $tag = $this->expandTag((string) ($this->argument('b') ?: '1'));
        if ($target === '' || $target === 'g' || $target === 'gate') {
            $this->error('Usage: ir4:s r {1-4} [tag]  (poles only; no gate)');

            return self::FAILURE;
        }

        $this->postTag($client, 'DEV-RFID-0'.$this->pole($target), $tag);

        return self::SUCCESS;
    }

    private function runArrive(StandbyPoleIngest $client): int
    {
        $pole = $this->pole((string) $this->argument('a'));
        $tag = $this->expandTag((string) ($this->argument('b') ?: '1'));
        $this->postTag($client, 'DEV-RFID-0'.$pole, $tag);
        $this->info("rfid pole-0{$pole} (poles only; no gate)");

        return self::SUCCESS;
    }

    private function postTag(StandbyPoleIngest $client, string $ref, string $tag): void
    {
        $cred = $this->cred($ref);
        $rssi = round(-52 - (mt_rand(0, 160) / 10), 1);
        $result = $client->postJson($cred['token'], '/api/ingest/tag-readings', [
            'events' => [[
                'event_uid' => (string) Str::uuid(),
                'reader_ref' => $ref,
                'tag_uid' => $tag,
                'recorded_at' => now()->toIso8601String(),
                'rssi' => $rssi,
                'antenna' => mt_rand(1, 2),
            ]],
        ]);
        $this->info("rfid {$ref} tag={$tag} rssi={$rssi} http={$result['status']}");
    }

    private function runPpe(StandbyPoleIngest $client, string $type): int
    {
        $pole = $this->pole((string) $this->argument('a'));
        $ref = 'DEV-CAM-FIXED-0'.$pole;
        $camera = 'CAM-FIXED-0'.$pole;
        $cred = $this->cred($ref);
        $result = $client->postJson($cred['token'], '/api/ingest/ppe-violations', [
            'events' => [[
                'event_uid' => (string) Str::uuid(),
                'camera_ref' => $camera,
                'event_type' => $type,
                'detected_at' => now()->toIso8601String(),
                'confidence' => round(0.82 + (mt_rand(0, 12) / 100), 2),
                'worker_count' => 1,
            ]],
        ]);
        $this->info("ppe {$type} {$camera} (no snapshot) http={$result['status']}");

        return self::SUCCESS;
    }

    private function runGasBump(StandbyPoleIngest $client): int
    {
        $pole = $this->pole((string) $this->argument('a'));
        $this->postAmbientGas($client, $pole, bump: true);
        $this->info("gas bump pole-0{$pole} (one elevated sample; next tick returns to ambient)");

        return self::SUCCESS;
    }

    private function postAmbientGas(StandbyPoleIngest $client, int $pole, bool $bump = false): void
    {
        $ref = 'DEV-GAS-0'.$pole;
        $cred = $this->cred($ref);
        $jitter = static fn (float $base, float $span): float => round($base + ((mt_rand(0, 1000) / 1000) * $span), 2);
        $fields = $bump
            ? [
                'lel_pct' => $jitter(0.4, 0.3),
                'h2s_ppm' => $jitter(4.0, 1.5),
                'o2_pct' => $jitter(20.6, 0.2),
                'co_ppm' => $jitter(18.0, 6.0),
                'co2_ppm' => $jitter(1850, 250),
            ]
            : [
                'lel_pct' => $jitter(0.0, 0.15),
                'h2s_ppm' => $jitter(0.0, 0.4),
                'o2_pct' => $jitter(20.75, 0.2),
                'co_ppm' => $jitter(0.4, 1.6),
                'co2_ppm' => $jitter(410, 70),
            ];
        $client->postJson($cred['token'], '/api/ingest/gas-readings', [
            'events' => [array_merge([
                'event_uid' => (string) Str::uuid(),
                'device_ref' => $ref,
                'recorded_at' => now()->toIso8601String(),
            ], $fields)],
        ]);
    }

    private function heartbeatPole(StandbyPoleIngest $client, int $pole): void
    {
        foreach (['DEV-GAS-0', 'DEV-RFID-0', 'DEV-CAM-FIXED-0', 'DEV-CAM-PTZ-0'] as $prefix) {
            $cred = $this->cred($prefix.$pole);
            $client->heartbeat($cred['uuid'], $cred['token']);
        }
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
            throw new RuntimeException('Pole must be 1–4 (SCC2).');
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
