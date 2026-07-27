<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthLockoutService;
use Illuminate\Console\Command;

/**
 * Admin-initiated password reset with no outbound email (DOC-02 §6.3).
 *
 * Generates a temporary password, forces must_change_password, clears lockout
 * counters, and prints the plaintext once to the console for hand-off.
 *
 * Usage:
 *   php artisan ir4:user:reset operator@example.com
 */
final class ResetUserCommand extends Command
{
    protected $signature = 'ir4:user:reset {email : The user email to reset}';

    protected $description = 'Reset a user password (console fallback; no email)';

    public function handle(AuthLockoutService $lockout): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found for {$email}");

            return self::FAILURE;
        }

        // Service sets temporary password + must_change_password and audits.
        $plain = $lockout->resetPassword($user);
        $lockout->clearFailures($email);

        $this->info("Password reset for {$email}");
        $this->line("Temporary password (shown once): {$plain}");
        $this->warn('User must change password on next login.');

        return self::SUCCESS;
    }
}
