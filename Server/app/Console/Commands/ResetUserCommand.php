<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthLockoutService;
use Illuminate\Console\Command;

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

        $plain = $lockout->resetPassword($user);
        $lockout->clearFailures($email);

        $this->info("Password reset for {$email}");
        $this->line("Temporary password (shown once): {$plain}");
        $this->warn('User must change password on next login.');

        return self::SUCCESS;
    }
}
