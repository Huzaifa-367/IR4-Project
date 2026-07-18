<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthLockoutService
{
    public function isLocked(string $email): bool
    {
        return Cache::has($this->lockKey($email));
    }

    public function remainingLockSeconds(string $email): int
    {
        $expiresAt = Cache::get($this->lockKey($email));

        if (! is_numeric($expiresAt)) {
            return 0;
        }

        return max(0, (int) $expiresAt - now()->getTimestamp());
    }

    public function recordFailure(string $email): void
    {
        $key = $this->attemptsKey($email);
        $attempts = (int) Cache::get($key, 0) + 1;
        $lockoutMinutes = (int) app(SettingsService::class)->get('auth.lockout_minutes', 15);
        $threshold = (int) app(SettingsService::class)->get('auth.lockout_threshold', 10);

        Cache::put($key, $attempts, now()->addMinutes($lockoutMinutes));

        if ($attempts >= $threshold) {
            Cache::put(
                $this->lockKey($email),
                now()->addMinutes($lockoutMinutes)->getTimestamp(),
                now()->addMinutes($lockoutMinutes),
            );
            Cache::forget($key);
        }
    }

    public function clearFailures(string $email): void
    {
        Cache::forget($this->attemptsKey($email));
        Cache::forget($this->lockKey($email));
    }

    /**
     * Admin / artisan reset: set temporary password, force change, invalidate sessions.
     *
     * @return string Plaintext temporary password (shown once)
     */
    public function resetPassword(User $user, ?string $temporaryPassword = null): string
    {
        $plain = $temporaryPassword ?? Str::password(16);

        $user->forceFill([
            'password' => Hash::make($plain),
            'must_change_password' => true,
            'password_changed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        // Invalidate other sessions when using database/redis session driver.
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        return $plain;
    }

    private function attemptsKey(string $email): string
    {
        return 'auth.login_attempts.'.Str::lower($email);
    }

    private function lockKey(string $email): string
    {
        return 'auth.lockout.'.Str::lower($email);
    }
}
