<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalogue;
use Database\Seeders\GasThresholdSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class InstallCommand extends Command
{
    protected $signature = 'ir4:install
                            {--name= : Super Admin name}
                            {--email= : Super Admin email}
                            {--password= : Super Admin password (shown/stored; must_change_password=true)}';

    protected $description = 'Seed permissions/roles/settings and create the first Super Admin user';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => GasThresholdSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        if (User::query()->role('Super Admin')->exists()) {
            $this->warn('A Super Admin user already exists. Skipping user creation.');

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Super Admin name', 'Super Admin');
        $email = $this->option('email') ?: $this->ask('Super Admin email', 'admin@ir4.local');
        $password = $this->option('password') ?: $this->secret('Super Admin password') ?: 'ChangeMeNow1!';

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

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $role = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->firstOrFail();

        $user->assignRole($role);

        $this->info("Installed. Super Admin: {$email}");
        $this->warn('User must change password on first login.');

        return self::SUCCESS;
    }
}
