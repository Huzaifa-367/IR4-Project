<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserProvisioningService;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('seeds the full permission catalogue and starter roles', function () {
    expect(Permission::query()->where('guard_name', 'web')->count())
        ->toBe(count(PermissionCatalogue::all()));

    foreach ([
        'Super Admin',
        'Safety Manager',
        'SCC Operator',
        'Project Manager',
        'Client Representative',
        'Field Staff',
    ] as $name) {
        expect(Role::query()->where('name', $name)->exists())->toBeTrue();
    }

    $super = Role::query()->where('name', 'Super Admin')->firstOrFail();
    expect($super->is_system)->toBeTrue()
        ->and($super->permissions)->toHaveCount(count(PermissionCatalogue::all()));

    $fieldStaff = Role::query()->where('name', 'Field Staff')->firstOrFail();
    expect($fieldStaff->permissions)->toHaveCount(0);
});

it('blocks editing the Super Admin role', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $role = Role::query()->where('name', 'Super Admin')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('settings.roles.update', $role), [
            'name' => 'Super Admin',
            'permissions' => ['view-dashboard'],
        ])
        ->assertForbidden();
});

it('rejects non view-* permissions on read-only roles', function () {
    $service = app(RoleService::class);
    $role = Role::query()->where('name', 'Client Representative')->firstOrFail();

    expect(fn () => $service->syncPermissions($role, ['manage-users']))
        ->toThrow(ValidationException::class);
});

it('allows view-* permissions on read-only roles', function () {
    $service = app(RoleService::class);
    $role = Role::query()->where('name', 'Client Representative')->firstOrFail();

    $service->syncPermissions($role, ['view-dashboard', 'view-reports']);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['view-dashboard', 'view-reports']);
});

it('forbids demoting the last active Super Admin', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $operatorRole = Role::query()->where('name', 'SCC Operator')->firstOrFail();

    expect(fn () => app(UserProvisioningService::class)->assignRole($admin, $operatorRole))
        ->toThrow(HttpException::class);
});

it('forbids deactivating the last active Super Admin', function () {
    $admin = User::factory()->withRole('Super Admin')->create();

    expect(fn () => app(UserProvisioningService::class)->deactivate($admin))
        ->toThrow(HttpException::class);
});

it('blocks dashboard access without view-dashboard', function () {
    $user = User::factory()->withRole('Field Staff')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('allows dashboard access for SCC Operator', function () {
    $user = User::factory()->create();

    expect($user->can('view-dashboard'))->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('gates roles settings behind manage-roles', function () {
    $operator = User::factory()->create();
    $admin = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($operator)
        ->get(route('settings.roles.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('settings.roles.index'))
        ->assertOk();
});

it('creates the first Super Admin via ir4:install', function () {
    User::query()->delete();

    Artisan::call('ir4:install', [
        '--name' => 'Root',
        '--email' => 'root@ir4.local',
        '--password' => 'ChangeMeNow1!',
    ]);

    $user = User::query()->where('email', 'root@ir4.local')->firstOrFail();

    expect($user->hasRole('Super Admin'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();
});

it('exports PERMISSIONS.md and Permission union', function () {
    $md = base_path('PERMISSIONS.md');
    $enums = resource_path('js/types/enums.ts');

    if (file_exists($md)) {
        unlink($md);
    }

    Artisan::call('ir4:export-permissions');

    expect(file_exists($md))->toBeTrue()
        ->and(file_get_contents($md))->toContain('# IR4 Permissions')
        ->and(file_get_contents($enums))->toContain('export const Permission')
        ->and(file_get_contents($enums))->toContain("ViewDashboard: 'view-dashboard'");
});
