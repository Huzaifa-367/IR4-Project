<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\RoleService;

// DOC-21 scenario 11: Read-only client cannot write; data_access logged on meaningful reads.
it('scenario 11: read-only client logs data_access and cannot reach write routes', function () {
    $clientRole = Role::query()->where('name', 'Client Representative')->firstOrFail();
    app(RoleService::class)->syncPermissions($clientRole, [
        'view-dashboard',
        'view-reports',
    ]);

    $client = User::factory()->withRole('Client Representative')->create();

    $before = AuditLog::query()->where('event', AuditEvent::DataAccess)->count();

    $this->actingAs($client)
        ->get(route('dashboard'))
        ->assertOk();

    $published = WeeklyReport::factory()->published()->create();
    $this->get(route('reports.show', $published))
        ->assertOk();

    expect(AuditLog::query()->where('event', AuditEvent::DataAccess)->count())->toBe($before + 2);

    $this->post(route('weekly-reports.generate'), [
        'period_start' => now()->toDateString(),
        'period_end' => now()->toDateString(),
    ])->assertForbidden();

    $this->post(route('hse.incidents.store'), [
        'occurred_at' => now()->toDateTimeString(),
        'nature_of_incident' => 'Should not be allowed for read-only client.',
    ])->assertForbidden();
});
