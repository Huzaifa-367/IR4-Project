<?php

use App\Models\Device;
use App\Models\User;
use App\Services\AuthLockoutService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Hash;

it('rejects registration routes', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

it('rejects email password reset routes', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password')->assertNotFound();
});

it('rejects email verification routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/email/verify')->assertNotFound();
});

it('sets last_login_at on successful login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('blocks inactive users with a generic message', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks an account after too many failures', function () {
    $user = User::factory()->create();
    $lockout = app(AuthLockoutService::class);

    for ($i = 0; $i < 10; $i++) {
        $lockout->recordFailure($user->email);
    }

    expect($lockout->isLocked($user->email))->toBeTrue();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('forces password change before dashboard', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('force-password.edit'));

    $this->actingAs($user)
        ->get(route('force-password.edit'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('force-password.update'), [
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse();
});

it('logs out when idle timeout is exceeded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['last_activity_at' => now()->subMinutes(20)->getTimestamp()])
        ->get(route('dashboard'))
        ->assertRedirect(route('login', ['timeout' => 1]));

    $this->assertGuest();
});

it('extends the session via heartbeat', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['last_activity_at' => now()->subMinutes(10)->getTimestamp()])
        ->postJson(route('session.heartbeat'))
        ->assertOk()
        ->assertJsonPath('data.ok', true);

    expect(session('last_activity_at'))->toBeGreaterThan(now()->subMinute()->getTimestamp());
});

it('requires auth for the display view', function () {
    $this->get(route('display'))->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('display'))
        ->assertOk();
});

it('authenticates devices via X-Device-Token', function () {
    $plain = 'test-device-token-plain';
    $device = Device::factory()->withPlainToken($plain)->create();
    \App\Models\RfidTag::factory()->create(['tag_uid' => 'TAG-AUTH']);

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [[
            'event_uid' => (string) \Illuminate\Support\Str::uuid(),
            'reader_ref' => $device->reference,
            'tag_uid' => 'TAG-AUTH',
            'recorded_at' => now()->toIso8601String(),
        ]],
    ], [
        'X-Device-Token' => $plain,
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', 1);

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [[
            'event_uid' => (string) \Illuminate\Support\Str::uuid(),
            'reader_ref' => $device->reference,
            'tag_uid' => 'TAG-AUTH',
            'recorded_at' => now()->toIso8601String(),
        ]],
    ])->assertUnauthorized();

    $retired = Device::factory()->withPlainToken('retired-token')->retired()->create();

    $this->postJson(route('api.ingest.tag-readings'), [
        'events' => [[
            'event_uid' => (string) \Illuminate\Support\Str::uuid(),
            'reader_ref' => $retired->reference,
            'tag_uid' => 'TAG-AUTH',
            'recorded_at' => now()->toIso8601String(),
        ]],
    ], [
        'X-Device-Token' => 'retired-token',
    ])->assertForbidden();

    expect($retired->fresh()->id)->toBeInt();
});

it('rejects device tokens on operator routes', function () {
    $plain = 'operator-should-reject';
    Device::factory()->withPlainToken($plain)->create();

    $this->get(route('dashboard'), [
        'X-Device-Token' => $plain,
    ])->assertRedirect(route('login'));
});

it('resets a user via artisan console', function () {
    $user = User::factory()->create([
        'email' => 'ops@example.com',
        'must_change_password' => false,
    ]);

    $this->artisan('ir4:user:reset', ['email' => 'ops@example.com'])
        ->assertSuccessful();

    expect($user->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeFalse();
});

it('reads auth lockout settings defaults', function () {
    $settings = app(SettingsService::class);

    expect($settings->get('auth.lockout_threshold'))->toBe(10)
        ->and($settings->get('auth.session_timeout_minutes'))->toBe(15);
});
