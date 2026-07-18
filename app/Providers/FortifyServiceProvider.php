<?php

namespace App\Providers;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthLockoutService;
use App\Services\SettingsService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureLoginSideEffects();
    }

    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower((string) $request->input(Fortify::username()));
            $lockout = app(AuthLockoutService::class);

            if ($lockout->isLocked($email)) {
                app(AuditService::class)->record(
                    AuditEvent::LoginFailed,
                    description: 'Login failed: account locked.',
                    newValues: ['email' => $email, 'reason' => 'locked'],
                );
                throw ValidationException::withMessages([
                    Fortify::username() => __('Account temporarily locked. Try again later.'),
                ]);
            }

            /** @var User|null $user */
            $user = User::query()->where('email', $email)->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                $lockout->recordFailure($email);
                app(AuditService::class)->record(
                    AuditEvent::LoginFailed,
                    $user,
                    'Login failed: bad credentials.',
                    newValues: ['email' => $email, 'reason' => 'bad_credentials'],
                    user: $user,
                );

                return null;
            }

            if (! $user->is_active) {
                $lockout->recordFailure($email);
                app(AuditService::class)->record(
                    AuditEvent::LoginFailed,
                    $user,
                    'Login failed: inactive account.',
                    newValues: ['email' => $email, 'reason' => 'inactive'],
                    user: $user,
                );

                throw ValidationException::withMessages([
                    Fortify::username() => __('These credentials do not match our records.'),
                ]);
            }

            $lockout->clearFailures($email);

            if (
                (bool) app(SettingsService::class)->get('auth.require_2fa_for_admins', false)
                && $user->can('manage-users')
                && $user->two_factor_confirmed_at === null
            ) {
                app(AuditService::class)->record(
                    AuditEvent::LoginFailed,
                    $user,
                    'Login failed: admin 2FA required.',
                    newValues: ['email' => $email, 'reason' => 'admin_2fa_required'],
                    user: $user,
                );

                throw ValidationException::withMessages([
                    Fortify::username() => __('Two-factor authentication is required for administrator accounts.'),
                ]);
            }

            return $user;
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
            'timeout' => $request->boolean('timeout'),
            'locked' => $request->boolean('locked'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            $max = (int) app(SettingsService::class)->get('auth.login_max_per_min', 5);

            return Limit::perMinute(max(1, $max))->by($throttleKey);
        });
    }

    private function configureLoginSideEffects(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            /** @var User $user */
            $user = $event->user;
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->save();
            app(AuditService::class)->record(
                AuditEvent::Login,
                $user,
                'User logged in.',
                user: $user,
            );

            session(['last_activity_at' => now()->getTimestamp()]);
        });
        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user instanceof User ? $event->user : null;
            $reason = request()->attributes->get('logout_reason', 'user');
            app(AuditService::class)->record(
                AuditEvent::Logout,
                $user,
                $reason === 'idle_timeout' ? 'User logged out after idle timeout.' : 'User logged out.',
                newValues: ['reason' => $reason],
                user: $user,
            );
        });
    }
}
