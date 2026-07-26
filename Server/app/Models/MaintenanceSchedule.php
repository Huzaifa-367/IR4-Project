<?php

namespace App\Models;

use App\Enums\ScheduleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MaintenanceSchedule extends Model
{
    /** @use HasFactory<MaintenanceScheduleFactory> */
    use Auditable, HasCreatedBy, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule_type' => ScheduleType::class,
            'interval_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
