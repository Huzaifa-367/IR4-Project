<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Database\Factories\WeeklyReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WeeklyReport extends Model
{
    /** @use HasFactory<WeeklyReportFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === ReportStatus::Published;
    }

    public function isImmutable(): bool
    {
        return $this->status === ReportStatus::Published;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return BelongsTo<WeeklyReport, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_report_id');
    }

    /**
     * @return HasMany<WeeklyReport, $this>
     */
    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_report_id');
    }
}
