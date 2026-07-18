<?php

use App\Enums\AuditEvent;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('records masked configuration diffs and blocks model mutation', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $device = Device::factory()->create([
        'device_type' => DeviceType::RfidReader,
        'status' => HardwareStatus::Online,
        'api_token_hash' => hash('sha256', 'old-secret'),
    ]);

    $this->actingAs($manager);
    $device->forceFill([
        'status' => HardwareStatus::Offline,
        'api_token_hash' => hash('sha256', 'new-secret'),
    ])->save();

    $audit = AuditLog::query()
        ->where('event', AuditEvent::ConfigChanged)
        ->where('auditable_type', $device->getMorphClass())
        ->where('auditable_id', $device->id)
        ->latest('id')
        ->firstOrFail();

    expect($audit->user_id)->toBe($manager->id)
        ->and($audit->old_values['status'])->toBe(HardwareStatus::Online->value)
        ->and($audit->new_values['status'])->toBe(HardwareStatus::Offline->value)
        ->and($audit->old_values['api_token_hash'])->toBe('••••')
        ->and($audit->new_values['api_token_hash'])->toBe('••••')
        ->and(json_encode([$audit->old_values, $audit->new_values]))->not->toContain('new-secret');

    expect(fn () => $audit->forceFill(['description' => 'tampered'])->save())
        ->toThrow(\LogicException::class);
    expect(fn () => $audit->delete())->toThrow(\LogicException::class);
});

it('logs only meaningful reads for read-only roles', function () {
    $projectManager = User::factory()->withRole('Project Manager')->create();
    $before = AuditLog::query()->where('event', AuditEvent::DataAccess)->count();

    $this->actingAs($projectManager)->get(route('dashboard'))->assertOk();
    $this->getJson(route('dashboard.summary'))->assertOk();

    $logs = AuditLog::query()
        ->where('event', AuditEvent::DataAccess)
        ->where('user_id', $projectManager->id)
        ->get();

    expect(AuditLog::query()->where('event', AuditEvent::DataAccess)->count())->toBe($before + 1)
        ->and($logs)->toHaveCount(1)
        ->and($logs->first()->route)->toBe('dashboard');
});

it('gates the viewer and audits CSV exports', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get(route('settings.audit-log.index'))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('settings.audit-log.index', ['event' => AuditEvent::Created->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/audit-log/index')
            ->has('auditLogs.data')
            ->where('filters.event', AuditEvent::Created->value));

    $this->get(route('settings.audit-log.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect(AuditLog::query()
        ->where('event', AuditEvent::Exported)
        ->where('user_id', $manager->id)
        ->where('route', 'settings.audit-log.export')
        ->exists())->toBeTrue();
});

it('records login, failed login and logout with request context', function () {
    $user = User::factory()->create([
        'email' => 'audit@example.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->post(route('logout'))->assertRedirect(route('home'));

    expect(AuditLog::query()->where('event', AuditEvent::LoginFailed)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::Login)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::Logout)->where('user_id', $user->id)->exists())->toBeTrue();

    $login = AuditLog::query()->where('event', AuditEvent::Login)->where('user_id', $user->id)->firstOrFail();
    expect($login->ip_address)->not->toBeNull()
        ->and($login->user_agent)->not->toBeNull();
});
