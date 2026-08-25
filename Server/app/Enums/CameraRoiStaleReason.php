<?php

namespace App\Enums;

enum CameraRoiStaleReason: string
{
    case Ptz = 'ptz';
    case StreamChanged = 'stream_changed';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Ptz => 'PTZ moved',
            self::StreamChanged => 'Stream config changed',
            self::Manual => 'Marked stale manually',
        };
    }
}
