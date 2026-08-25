<?php

namespace App\Enums;

enum DeviceType: string
{
    case GasDetector = 'gas_detector';
    case EnvironmentalSensor = 'environmental_sensor';
    case RfidReader = 'rfid_reader';
    case QrPrinter = 'qr_printer';
    case Camera = 'camera';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GasDetector => 'Gas reader',
            self::EnvironmentalSensor => 'Environmental',
            self::RfidReader => 'RFID reader',
            self::QrPrinter => 'QR printer',
            self::Camera => 'Camera',
            self::Other => 'Other',
        };
    }

    public function usesIngestToken(): bool
    {
        return $this !== self::QrPrinter;
    }

    public function isHealthMonitored(): bool
    {
        return $this->usesIngestToken();
    }

    public function isCamera(): bool
    {
        return $this === self::Camera;
    }

    public function isStreamDevice(): bool
    {
        return $this === self::Camera;
    }

    /**
     * @return list<self>
     */
    public static function registryTypes(): array
    {
        return [
            self::GasDetector,
            self::RfidReader,
            self::Camera,
            self::EnvironmentalSensor,
            self::QrPrinter,
            self::Other,
        ];
    }

    public function staleMinutesKey(): string
    {
        return match ($this) {
            self::RfidReader => 'health.reader_stale_minutes',
            self::GasDetector => 'health.gas_stale_minutes',
            self::Camera => 'health.edge_stale_minutes',
            self::EnvironmentalSensor,
            self::Other => 'health.sensor_stale_minutes',
            self::QrPrinter => 'health.sensor_stale_minutes',
        };
    }

    public function defaultStaleMinutes(): int
    {
        return match ($this) {
            self::Camera => 3,
            default => 5,
        };
    }
}
