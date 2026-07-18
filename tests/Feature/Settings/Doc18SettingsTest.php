<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\SettingsRegistry;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

it('falls back to registry defaults and rejects unknown keys', function () {
    $service = app(SettingsService::class);

    expect($service->get('tracking.gate_debounce_seconds'))->toBe(60);

    expect(fn () => $service->set('arbitrary.key', 1))
        ->toThrow(ValidationException::class);
});

it('validates type/range and audits config_changed with old to new', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $this->actingAs($admin);
    $service = app(SettingsService::class);

    $service->set('tracking.gate_debounce_seconds', 90);

    expect($service->get('tracking.gate_debounce_seconds'))->toBe(90);

    $audit = AuditLog::query()
        ->where('event', AuditEvent::ConfigChanged)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->old_values['key'] ?? null)->toBe('tracking.gate_debounce_seconds')
        ->and($audit->old_values['value'] ?? null)->toBe(60)
        ->and($audit->new_values['value'] ?? null)->toBe(90);

    expect(fn () => $service->set('tracking.gate_debounce_seconds', 9999))
        ->toThrow(ValidationException::class);
});

it('requires confirmation for sensitive keys', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $this->actingAs($admin);
    $service = app(SettingsService::class);

    expect(fn () => $service->set('auth.session_timeout_minutes', 30))
        ->toThrow(ValidationException::class);

    $service->set('auth.session_timeout_minutes', 30, confirmed: true);

    expect($service->get('auth.session_timeout_minutes'))->toBe(30);
});

it('seeds missing keys only and never clobbers operator values', function () {
    Setting::query()->updateOrCreate(
        ['key' => 'tracking.gate_debounce_seconds'],
        ['value' => 120],
    );

    $this->seed(\Database\Seeders\SettingsSeeder::class);

    expect(app(SettingsService::class)->get('tracking.gate_debounce_seconds'))->toBe(120)
        ->and(Setting::query()->where('key', 'general.timezone')->exists())->toBeTrue()
        ->and(count(SettingsRegistry::keys()))->toBeGreaterThan(30);
});

it('gates the general settings editor by permission', function () {
    $pm = User::factory()->withRole('Project Manager')->create();
    $admin = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($pm)
        ->get(route('settings.general.edit'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('settings.general.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/general/index')
            ->has('groups'));

    $this->actingAs($admin)
        ->put(route('settings.general.update'), [
            'settings' => [
                'tracking.gate_debounce_seconds' => 75,
            ],
            'confirmed' => [],
        ])
        ->assertRedirect();

    expect(app(SettingsService::class)->get('tracking.gate_debounce_seconds'))->toBe(75);
});

it('applies gate debounce changes live without restart', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $this->actingAs($admin);
    app(SettingsService::class)->set('tracking.gate_debounce_seconds', 42);

    expect(app(SettingsService::class)->get('tracking.gate_debounce_seconds'))->toBe(42);
});
