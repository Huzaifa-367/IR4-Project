<?php

namespace App\Models;

use App\Enums\AccountedSource;
use App\Enums\MusterStatus;
use Database\Factories\EvacuationReportEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $evacuation_report_id
 * @property int $worker_id
 * @property int|null $last_zone_id
 * @property Carbon|null $last_seen_at
 * @property MusterStatus $muster_status
 * @property Carbon|null $accounted_at
 * @property AccountedSource|null $accounted_source
 * @property int|null $accounted_by
 */
final class EvacuationReportEntry extends Model
{
    /** @use HasFactory<EvacuationReportEntryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'muster_status' => MusterStatus::class,
            'accounted_source' => AccountedSource::class,
            'last_seen_at' => 'datetime',
            'accounted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EvacuationReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(EvacuationReport::class, 'evacuation_report_id');
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function lastZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'last_zone_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounted_by');
    }
}
