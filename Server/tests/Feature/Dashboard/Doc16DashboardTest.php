<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\IncidentStatus;
use App\Enums\ReportStatus;
use App\Models\Alert;
use App\Models\HseIncident;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\DashboardService;

it('returns permission-filtered dashboard summary for operators', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    Alert::factory()->create([
        'alert_type' => AlertType::FallDetection,
        'severity' => AlertSeverity::Critical,
        'status' => AlertStatus::Open,
        'title' => 'Critical fall',
    ]);
    HseIncident::factory()->create(['status' => IncidentStatus::Open]);
    WeeklyReport::factory()->published()->create();

    $this->actingAs($operator)
        ->getJson(route('dashboard.summary'))
        ->assertOk()
        ->assertJsonPath('data.headcount.total_on_site', 0)
        ->assertJsonPath('data.alerts.open_critical', 1)
        ->assertJsonStructure([
            'data' => [
                'headcount',
                'alerts',
                'weather',
                'system_health',
                'occupancy',
                'gas',
                'ppe_today',
                'incidents',
                'lsr',
                'equipment',
                'last_report',
            ],
        ]);
});

it('omits operational sections for project managers', function () {
    $pm = User::factory()->withRole('Project Manager')->create();
    WeeklyReport::factory()->generated()->create();
    WeeklyReport::factory()->published()->create(['report_number' => 'WR-PM-PUB']);

    $response = $this->actingAs($pm)
        ->getJson(route('dashboard.summary'))
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveKeys(['headcount', 'alerts', 'weather', 'system_health', 'equipment', 'last_report'])
        ->and($data)->not->toHaveKey('gas')
        ->and($data)->not->toHaveKey('occupancy')
        ->and($data)->not->toHaveKey('ppe_today')
        ->and($data['last_report']['status'])->toBe(ReportStatus::Published->value)
        ->and($data['headcount']['by_zone'])->toBe([]);
});

it('renders the dashboard for authorized users', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/index')
            ->has('summary')
            ->has('permissions')
            ->missing('cycleSeconds'));
});

it('forbids guests from the dashboard and has no kiosk route', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->getJson(route('dashboard.summary'))->assertUnauthorized();
    $this->get('/display')->assertNotFound();
});

it('builds summary via service with identity-safe occupancy labels', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    // Operator has view-worker-identity in seeder — strip by using a role without it.
    $pm = User::factory()->withRole('Project Manager')->create();

    $summary = app(DashboardService::class)->summary($pm);
    expect($summary)->not->toHaveKey('occupancy');

    $full = app(DashboardService::class)->summary($operator);
    expect($full)->toHaveKey('occupancy');
});
