<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\User;
use App\Models\WeeklyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyReport>
 */
class WeeklyReportFactory extends Factory
{
    protected $model = WeeklyReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->subWeek();
        $end = $start->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);

        return [
            'report_number' => 'WR-'.$start->format('Y').'-W'.$start->format('W').'-'.fake()->unique()->numerify('##'),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => ReportStatus::Draft,
            'data' => [
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                'completeness' => ['notes' => []],
            ],
            'generated_by' => null,
            'published_by' => null,
        ];
    }

    public function generated(): static
    {
        return $this->state(fn (): array => [
            'status' => ReportStatus::Generated,
            'generated_at' => now(),
            'generated_by' => User::factory(),
            'pdf_path' => 'reports/sample.pdf',
            'csv_path' => 'reports/sample.zip',
        ]);
    }

    public function published(): static
    {
        return $this->generated()->state(fn (): array => [
            'status' => ReportStatus::Published,
            'published_at' => now(),
            'published_by' => User::factory(),
        ]);
    }
}
