<?php

namespace App\Console\Commands;

use App\Services\Environment\WeatherIngestService;
use Illuminate\Console\Command;

final class FetchWeatherFromApiCommand extends Command
{
    protected $signature = 'ir4:fetch-weather-api';

    protected $description = 'Fetch OpenWeatherMap Current Weather when weather.source=api.';

    public function handle(WeatherIngestService $weatherIngest): int
    {
        $result = $weatherIngest->fetchSnapshot();

        return match ($result['status']) {
            'skipped' => $this->reportSkipped($result['detail'] ?? ''),
            'failed' => $this->reportFailed($result['detail'] ?? ''),
            'stored' => $this->reportStored($result['detail'] ?? ''),
            default => self::SUCCESS,
        };
    }

    private function reportSkipped(string $detail): int
    {
        $this->line($detail !== '' ? "Skipped — {$detail}." : 'Skipped.');

        return self::SUCCESS;
    }

    private function reportFailed(string $detail): int
    {
        $this->warn($detail !== '' ? "OpenWeatherMap fetch failed: {$detail}" : 'OpenWeatherMap fetch failed.');

        return self::SUCCESS;
    }

    private function reportStored(string $detail): int
    {
        $this->info($detail !== '' ? "Weather snapshot stored at {$detail}" : 'Weather snapshot stored.');

        return self::SUCCESS;
    }
}
