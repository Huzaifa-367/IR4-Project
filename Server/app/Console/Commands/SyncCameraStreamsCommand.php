<?php

namespace App\Console\Commands;

use App\Services\Camera\CameraStreamGatewayService;
use Illuminate\Console\Command;

final class SyncCameraStreamsCommand extends Command
{
    protected $signature = 'ir4:sync-camera-streams {--probe : Only test MediaMTX API reachability}';

    protected $description = 'Push all camera RTSP URLs into MediaMTX for the live wall';

    public function handle(CameraStreamGatewayService $gateway): int
    {
        if (! $gateway->isConfigured()) {
            $this->warn('MEDIAMTX_API_URL is not set — nothing to sync.');

            return self::SUCCESS;
        }

        $gateway->forgetResolvedApiBase();

        $apiUrl = (string) config('camera_stream.mediamtx.api_url');
        $apiUser = trim((string) config('camera_stream.mediamtx.api_user'));
        $hostIp = trim((string) config('camera_stream.mediamtx.host_ip'));
        $resolved = $gateway->apiBaseUrl();
        $this->line('MediaMTX API config: '.$apiUrl);
        if ($hostIp !== '') {
            $this->line('MediaMTX host IP: '.$hostIp);
        }
        $this->line('MediaMTX API resolved: '.$resolved);
        $this->line('API basic auth: '.($apiUser !== '' ? 'yes (user='.$apiUser.')' : 'no'));
        if (($gw = $gateway->detectDefaultGateway()) !== null) {
            $this->line('Container default gateway: '.$gw.' (Podman pasta — often NOT the host)');
        }
        $this->line('Host candidates: '.implode(', ', $gateway->hostCandidates()));

        if ($this->option('probe')) {
            $probe = $gateway->probe();
            if ($probe['ok']) {
                $this->info($probe['message']);
                $this->line($probe['body']);

                return self::SUCCESS;
            }

            $this->error($probe['message']);
            $this->printHints($apiUser !== '');

            return self::FAILURE;
        }

        $probe = $gateway->probe();
        if (! $probe['ok']) {
            $this->error('Probe failed before sync: '.$probe['message']);
            $this->printHints($apiUser !== '');

            return self::FAILURE;
        }

        $result = $gateway->syncAll();
        $this->info('Synced '.$result['synced'].' camera path(s) to MediaMTX.');

        if ($result['failed'] > 0) {
            $this->error('Failed '.$result['failed'].': '.implode(', ', $result['errors']));
            if ($result['detail'] !== '') {
                $this->error('First error: '.$result['detail']);
            }
            $this->printHints($apiUser !== '');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function printHints(bool $apiAuthEnabled): void
    {
        $this->warn(
            'Hints: (1) sudo bash scripts/03-ensure-mediamtx.sh. '
            .'(2) Set MEDIAMTX_API_URL=http://<SCC-LAN-IP>:9997 (e.g. http://192.168.3.149:9997) — Lerd/Podman cannot use 127.0.0.1 or pasta gateway 10.89.x.x. '
            .'(3) Or MEDIAMTX_API_URL=gateway with MEDIAMTX_HOST_IP=<SCC-LAN-IP>. '
            .'(4) Leave MEDIAMTX_API_USER/PASS empty. '
            .'(5) Host curl http://127.0.0.1:9997/v3/config/paths/list must not say authentication error.'
        );
        if ($apiAuthEnabled) {
            $this->warn('API basic auth is currently ON — if host `curl` works without -u, clear MEDIAMTX_API_USER and MEDIAMTX_API_PASS, then: lerd artisan config:clear');
        }
    }
}
