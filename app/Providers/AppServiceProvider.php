<?php

namespace App\Providers;

use App\Services\SettingsService;
use App\Services\SignedStorageUrlService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(SignedStorageUrlService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureRuntimeTimezone();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // On-prem: no outbound HaveIBeenPwned check (DOC-02 / DOC-18).
        Password::defaults(function (): Password {
            $minLength = 12;
            try {
                $minLength = max(8, (int) app(SettingsService::class)->get('auth.password_min_length', 12));
            } catch (Throwable) {
                // Settings table may be unavailable during early boot / migrate.
            }

            return app()->isProduction()
                ? Password::min($minLength)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                : Password::min(min(8, $minLength));
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('ingest', function (Request $request) {
            $device = $request->attributes->get('device');
            $max = (int) app(SettingsService::class)->get('ingest.rate_per_minute', 120);
            $key = $device !== null ? 'ingest:'.$device->id : 'ingest:'.$request->ip();

            return Limit::perMinute(max(1, $max))->by($key);
        });

        RateLimiter::for('equipment.public', function (Request $request) {
            $max = (int) app(SettingsService::class)->get('equipment.public_rate_limit_per_min', 30);

            return Limit::perMinute(max(1, $max))->by($request->ip() ?? 'equipment-public');
        });
    }

    protected function configureRuntimeTimezone(): void
    {
        try {
            $timezone = (string) app(SettingsService::class)->get('general.timezone', config('app.timezone'));
            if ($timezone !== '') {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        } catch (Throwable) {
            // Settings unavailable during early migrate/install.
        }
    }
}
