<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalogue;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DeviceCredentialsSeeder;
use Database\Seeders\GasThresholdSeeder;
use Database\Seeders\PermitCatalogueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * First-time platform bootstrap (DOC-03 §7.3, DOC-20).
 *
 * Seeds settings, RBAC, gas thresholds, then creates the initial Super Admin
 * (interactive or via --name/--email/--password). Initial site registry +
 * permit catalogue run after the admin exists so rows attach to that user.
 * DemoSeeder output is shown (device credentials for EdgeCompute).
 *
 * Usage:
 *   php artisan ir4:install
 *   php artisan ir4:install --name="…" --email="…" --password="…"
 */
final class InstallCommand extends Command
{
    protected $signature = 'ir4:install
                            {--name= : Super Admin name}
                            {--email= : Super Admin email}
                            {--password= : Super Admin password (shown/stored; must_change_password=true)}';

    protected $description = 'Seed permissions/roles/settings and create the first Super Admin user';

    public function handle(): int
    {
        // Baseline config + RBAC + gas alarm thresholds (safe to re-run).
        $this->callSilent('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => GasThresholdSeeder::class, '--force' => true]);

        // Create Super Admin before site registry so hardware rows attach to this
        // account instead of inventing a different admin and ignoring CLI options.
        if (! $this->ensureSuperAdmin()) {
            return self::FAILURE;
        }

        // Initial site registry (poles/devices/cameras) — not silent so device
        // credentials (UUID + plaintext token) print for EdgeCompute secrets.
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => DeviceCredentialsSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => PermitCatalogueSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }

    /**
     * Create the first Super Admin if none exists; otherwise skip and succeed.
     */
    private function ensureSuperAdmin(): bool
    {
        if (User::query()->role('Super Admin')->exists()) {
            $this->warn('A Super Admin user already exists. Skipping user creation.');

            return true;
        }

        // Options win; otherwise prompt (password hidden). Dev fallback password if empty.
        $name = $this->option('name') ?: $this->ask('Super Admin name', 'Super Admin');
        $email = $this->option('email') ?: $this->ask('Super Admin email', 'admin@gmail.com');
        $password = $this->option('password') ?: $this->secret('Super Admin password') ?: '12345677';

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return false;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            // DOC-02: first login must change temporary / installer password.
            'must_change_password' => true,
        ]);

        $role = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->firstOrFail();

        $user->assignRole($role);

        $this->info("Installed. Super Admin: {$email}");
        $this->warn('User must change password on first login.');

        return true;
    }
}
