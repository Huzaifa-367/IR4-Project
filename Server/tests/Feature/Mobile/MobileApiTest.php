<?php

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\User;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\Auth\AuthLockoutService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * @return array{user: User, token: string, headers: array<string, string>}
 */
function mobileAuthAs(string $role = 'Super Admin', array $userAttrs = []): array
{
    $user = User::factory()->withRole($role)->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
    ], $userAttrs));

    $plain = $user->createToken('IR4 Mobile Test')->plainTextToken;

    return [
        'user' => $user,
        'token' => $plain,
        'headers' => [
            'Authorization' => 'Bearer '.$plain,
            'Accept' => 'application/json',
        ],
    ];
}

it('logs in and returns a sanctum token with permissions', function () {
    $user = User::factory()->withRole('Super Admin')->create([
        'email' => 'operator@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson(route('api.mobile.login'), [
        'email' => 'operator@example.com',
        'password' => 'password',
        'device_name' => 'Test Phone',
    ])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'operator@example.com');

    expect($response->json('data.token'))->not->toBeEmpty()
        ->and($response->json('data.permissions'))->toContain('view-equipment')
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(1);
});

it('rejects bad credentials inactive accounts and locked accounts', function () {
    $user = User::factory()->withRole('SCC Operator')->create([
        'email' => 'operator@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->postJson(route('api.mobile.login'), [
        'email' => 'operator@example.com',
        'password' => 'wrong',
    ])->assertStatus(422);

    $user->forceFill(['is_active' => false])->save();
    $this->postJson(route('api.mobile.login'), [
        'email' => 'operator@example.com',
        'password' => 'password',
    ])->assertStatus(422);

    $user->forceFill(['is_active' => true])->save();
    $lockout = app(AuthLockoutService::class);
    for ($i = 0; $i < 10; $i++) {
        $lockout->recordFailure('operator@example.com');
    }

    $this->postJson(route('api.mobile.login'), [
        'email' => 'operator@example.com',
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('returns the authenticated user on me and requires a token', function () {
    $auth = mobileAuthAs('Super Admin');

    $this->getJson(route('api.mobile.me'), $auth['headers'])
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $auth['user']->uuid)
        ->assertJsonPath('data.user.email', $auth['user']->email);

    $this->app['auth']->forgetGuards();

    $this->getJson(route('api.mobile.me'), [
        'Accept' => 'application/json',
    ])->assertUnauthorized();
});

it('logs out by deleting the current access token', function () {
    $auth = mobileAuthAs('SCC Operator');

    $this->postJson(route('api.mobile.logout'), [], $auth['headers'])
        ->assertOk()
        ->assertJsonPath('data.logged_out', true);

    expect($auth['user']->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->getJson(route('api.mobile.me'), $auth['headers'])
        ->assertUnauthorized();
});

it('scans equipment by qr token and strips worker identity without permission', function () {
    $auth = mobileAuthAs('Project Manager');
    $equipment = Equipment::factory()->checkoutable()->create([
        'status' => EquipmentStatus::InService,
    ]);
    $worker = Worker::factory()->create(['name' => 'Secret Worker']);
    EquipmentCheckout::factory()->create([
        'equipment_id' => $equipment->id,
        'worker_id' => $worker->id,
        'returned_at' => null,
    ]);

    $response = $this->getJson(
        route('api.mobile.equipment.by-token', $equipment->qr_token),
        $auth['headers'],
    )
        ->assertOk()
        ->assertJsonPath('data.equipment.uuid', $equipment->uuid)
        ->assertJsonPath('data.equipment.checkout_state', 'checked_out');

    $holderName = $response->json('data.equipment.open_checkout.worker.name');
    expect($holderName)->not->toBe('Secret Worker')
        ->and($response->json('data.workers'))->not->toBeEmpty()
        ->and($response->json('data.zones'))->toBeArray()
        ->and($response->json('data.can_checkout'))->toBeFalse();
});

it('forbids equipment scan without view-equipment', function () {
    $auth = mobileAuthAs('Field Staff');
    $equipment = Equipment::factory()->create();

    $this->getJson(
        route('api.mobile.equipment.by-token', $equipment->qr_token),
        $auth['headers'],
    )->assertForbidden();
});

it('checks out and returns equipment through the mobile endpoints', function () {
    $auth = mobileAuthAs('Super Admin');
    $equipment = Equipment::factory()->checkoutable()->create([
        'status' => EquipmentStatus::InService,
    ]);
    $worker = Worker::factory()->create(['is_active' => true]);
    $zone = Zone::factory()->create(['is_active' => true]);

    $this->postJson(route('api.mobile.equipment.checkout', $equipment), [
        'worker_id' => $worker->id,
        'reason' => 'Scaffold work',
        'zone_id' => $zone->id,
        'condition_out' => 'good',
    ], $auth['headers'])
        ->assertCreated()
        ->assertJsonPath('data.equipment.checkout_state', 'checked_out')
        ->assertJsonPath('data.checkout.worker_id', $worker->id);

    $this->postJson(route('api.mobile.equipment.checkout', $equipment), [
        'worker_id' => $worker->id,
    ], $auth['headers'])->assertStatus(409);

    $this->postJson(route('api.mobile.equipment.return', $equipment), [
        'return_status' => 'ok',
        'condition_in' => 'good',
    ], $auth['headers'])
        ->assertOk()
        ->assertJsonPath('data.equipment.checkout_state', 'available');

    $this->postJson(route('api.mobile.equipment.return', $equipment), [], $auth['headers'])
        ->assertStatus(409);
});

it('rejects checkout of non-checkoutable equipment', function () {
    $auth = mobileAuthAs('Super Admin');
    $equipment = Equipment::factory()->create([
        'is_checkoutable' => false,
        'status' => EquipmentStatus::InService,
    ]);
    $worker = Worker::factory()->create(['is_active' => true]);

    $this->postJson(route('api.mobile.equipment.checkout', $equipment), [
        'worker_id' => $worker->id,
    ], $auth['headers'])->assertStatus(422);
});

it('rejects an unknown qr token with not found', function () {
    $auth = mobileAuthAs('Super Admin');

    $this->getJson(
        route('api.mobile.equipment.by-token', (string) Str::uuid()),
        $auth['headers'],
    )->assertNotFound();
});

it('rejects mobile login until the temporary password is changed', function () {
    User::factory()->withRole('SCC Operator')->mustChangePassword()->create([
        'email' => 'temp@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->postJson(route('api.mobile.login'), [
        'email' => 'temp@example.com',
        'password' => 'password',
    ])->assertStatus(422);
});

it('revokes mobile access when the account is deactivated', function () {
    $auth = mobileAuthAs('SCC Operator');
    $auth['user']->forceFill(['is_active' => false])->save();

    $this->getJson(route('api.mobile.me'), $auth['headers'])
        ->assertUnauthorized();
});
