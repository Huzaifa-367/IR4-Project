<?php

namespace App\Enums;

enum DeviceType: string
{
    case GasDetector = 'gas_detector';
    case EnvironmentalSensor = 'environmental_sensor';
    case RfidReader = 'rfid_reader';
    case WifiGateway = 'wifi_gateway';
    case Rs485Interface = 'rs485_interface';
    case QrPrinter = 'qr_printer';
    case EdgeCompute = 'edge_compute';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GasDetector => 'Gas detector',
            self::EnvironmentalSensor => 'Environmental sensor',
            self::RfidReader => 'RFID reader',
            self::WifiGateway => 'Wi-Fi gateway',
            self::Rs485Interface => 'RS485 interface',
            self::QrPrinter => 'QR printer',
            self::EdgeCompute => 'Edge compute',
            self::Other => 'Other',
        };
    }

    public function isHealthCritical(): bool
    {
        return $this !== self::QrPrinter;
    }
}
