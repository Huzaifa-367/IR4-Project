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

        $result = $gateway->syncAll();
        $this->info('Synced '.$result['synced'].' camera path(s) to MediaMTX.');

        return self::SUCCESS;
    }
}
