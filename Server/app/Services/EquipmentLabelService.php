<?php

namespace App\Services;

use App\Models\Equipment;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class EquipmentLabelService
{
    private const LABEL_DOTS = 400;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function publicUrl(Equipment $equipment): string
    {
        return rtrim((string) config('app.url'), '/').'/e/'.$equipment->qr_token;
    }

    public function png(Equipment $equipment, int $sizeMm = 50): string
    {
        $size = max(100, (int) round($sizeMm / 25.4 * 203));

        $result = (new Builder(
            writer: new PngWriter,
            data: $this->publicUrl($equipment),
            size: $size,
            margin: 10,
        ))->build();

        return $result->getString();
    }

    public function svg(Equipment $equipment, int $sizeMm = 50): string
    {
        $size = max(100, (int) round($sizeMm / 25.4 * 203));

        $result = (new Builder(
            writer: new SvgWriter,
            data: $this->publicUrl($equipment),
            size: $size,
            margin: 10,
        ))->build();

        return $result->getString();
    }

    public function zpl(Equipment $equipment): string
    {
        $url = $this->escapeZpl($this->publicUrl($equipment));
        $code = $this->escapeZpl($equipment->equipment_code);
        $dots = self::LABEL_DOTS;

        return implode("\n", [
            '^XA',
            "^PW{$dots}",
            "^LL{$dots}",
            '^LH0,0',
            "^FO40,40^BQN,2,5^FDQA,{$url}^FS",
            "^FO40,320^A0N,28,28^FD{$code}^FS",
            '^XZ',
        ])."\n";
    }

    /**
     * @param  Collection<int, Equipment>|iterable<Equipment>  $equipment
     */
    public function bulkZpl(iterable $equipment): string
    {
        $parts = [];

        foreach ($equipment as $item) {
            $parts[] = trim($this->zpl($item));
        }

        return implode("\n", $parts).(count($parts) > 0 ? "\n" : '');
    }

    /**
     * One-click print: TCP to ZT411 when configured, otherwise caller should download.
     *
     * @return array{sent: bool, printed: bool, zpl: string, host: ?string, error: ?string, message: string}
     */
    public function printLabel(Equipment $equipment): array
    {
        return $this->dispatchZpl($this->zpl($equipment));
    }

    /**
     * @param  Collection<int, Equipment>|iterable<Equipment>  $equipment
     * @return array{sent: bool, printed: bool, zpl: string, host: ?string, error: ?string, message: string}
     */
    public function printLabels(iterable $equipment): array
    {
        return $this->dispatchZpl($this->bulkZpl($equipment));
    }

    /**
     * @return array{sent: bool, printed: bool, zpl: string, host: ?string, error: ?string, message: string}
     */
    private function dispatchZpl(string $zpl): array
    {
        $host = config('ir4.equipment.printer_host');
        $port = (int) config('ir4.equipment.printer_port', 9100);

        if (! is_string($host) || trim($host) === '') {
            return [
                'sent' => false,
                'printed' => false,
                'zpl' => $zpl,
                'host' => null,
                'error' => 'Printer not configured.',
                'message' => 'Printer not configured — download ZPL instead.',
            ];
        }

        $host = trim($host);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);

        if ($socket === false) {
            Log::warning('equipment.printer_unreachable', [
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'error' => $errstr,
            ]);

            $error = $errstr !== '' ? $errstr : 'Printer unreachable.';

            return [
                'sent' => false,
                'printed' => false,
                'zpl' => $zpl,
                'host' => $host,
                'error' => $error,
                'message' => $error.' — download ZPL instead.',
            ];
        }

        stream_set_timeout($socket, 5);
        fwrite($socket, $zpl);
        fclose($socket);

        return [
            'sent' => true,
            'printed' => true,
            'zpl' => $zpl,
            'host' => $host,
            'error' => null,
            'message' => 'Sent to printer.',
        ];
    }

    private function escapeZpl(string $value): string
    {
        return str_replace(['^', '~', '\\'], ['', '', ''], $value);
    }
}
