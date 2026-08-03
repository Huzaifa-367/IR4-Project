<?php

namespace App\Console\Commands;

use App\Services\CameraStreamGatewayService;
use Illuminate\Console\Command;

final class SyncCameraStreamsCommand extends Command
{
    protected $signature = 'ir4:sync-camera-streams';

    protected $description = 'Push all camera RTSP URLs into MediaMTX for the live wall';

    public function handle(CameraStreamGatewayService $gateway): int
    {
        if (! $gateway->isConfigured()) {
            $this->warn('MEDIAMTX_API_URL is not set — nothing to sync.');

            return self::SUCCESS;
        }

        $apiUrl = (string) config('camera_stream.mediamtx.api_url');
        $this->line('MediaMTX API: '.$apiUrl);

        $result = $gateway->syncAll();
        $this->info('Synced '.$result['synced'].' camera path(s) to MediaMTX.');

        if ($result['failed'] > 0) {
            $this->error('Failed '.$result['failed'].': '.implode(', ', $result['errors']));
            $this->warn(
                'If artisan runs via `lerd`, 127.0.0.1 is the container — set MEDIAMTX_API_URL to the SCC host IP (e.g. http://192.168.3.149:9997) and leave MEDIAMTX_API_USER/PASS empty unless MediaMTX API auth is enabled.'
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
