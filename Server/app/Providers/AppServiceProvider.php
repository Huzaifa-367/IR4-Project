<?php

namespace App\Providers;

use App\Services\BackupStatusService;
use App\Services\SettingsService;
use App\Services\SignedStorageUrlService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupManifestWasCreated;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $mysql = config('database.connections.mysql');
        config([
            'database.default' => 'mysql',
            'database.connections' => [
                'mysql' => $mysql,
            ],
        ]);

        $this->app->singleton(SettingsService::class);
        $this->app->singleton(SignedStorageUrlService::class);

        // Spatie binds CleanupStrategy during packageRegistered() via config().
        // A stale bootstrap/cache/config.php (pre-backup install) makes that null
        // and breaks every artisan command. Re-bind after providers register.
        $this->app->booting(function (): void {
            $strategy = config('backup.cleanup.strategy');
            if (! is_string($strategy) || $strategy === '' || ! class_exists($strategy)) {
                $strategy = DefaultStrategy::class;
            }
            $this->app->bind(CleanupStrategy::class, $strategy);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureBackupEvents();
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

        RateLimiter::for('mobile-login', function (Request $request) {
            $max = (int) app(SettingsService::class)->get('auth.login_max_per_min', 5);
            $email = Str::lower((string) $request->input('email', ''));

            return Limit::perMinute(max(1, $max))->by($email.'|'.$request->ip());
        });
    }

    /**
     * Spatie mail/Slack channels stay empty (on-prem). Events drive AlertService.
     */
    protected function configureBackupEvents(): void
    {
        Event::listen(BackupManifestWasCreated::class, function (): void {
            $password = config('backup.backup.password');
            if (! is_string($password) || $password === '') {
                throw new RuntimeException('BACKUP_ARCHIVE_PASSWORD must be configured before creating a backup.');
            }
        });
        Event::listen(
            BackupWasSuccessful::class,
            fn (BackupWasSuccessful $event) => app(BackupStatusService::class)->recordSuccess($event),
        );
        Event::listen(
            BackupHasFailed::class,
            fn (BackupHasFailed $event) => app(BackupStatusService::class)->recordFailure($event),
        );
        Event::listen(
            UnhealthyBackupWasFound::class,
            fn (UnhealthyBackupWasFound $event) => app(BackupStatusService::class)->recordUnhealthy($event),
        );
        Event::listen(
            HealthyBackupWasFound::class,
            fn (HealthyBackupWasFound $event) => app(BackupStatusService::class)->recordHealthy($event),
        );
        Event::listen(
            CleanupHasFailed::class,
            fn (CleanupHasFailed $event) => app(BackupStatusService::class)->recordCleanupFailure($event),
        );
        Event::listen(
            CleanupWasSuccessful::class,
            fn (CleanupWasSuccessful $event) => app(BackupStatusService::class)->recordCleanupSuccess($event),
        );
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
