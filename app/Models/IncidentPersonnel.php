<?php

namespace App\Models;

use App\Enums\Involvement;
use Database\Factories\IncidentPersonnelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IncidentPersonnel extends Model
{
    /** @use HasFactory<IncidentPersonnelFactory> */
    use HasFactory;

    protected $table = 'incident_personnel';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'involvement' => Involvement::class,
        ];
    }

    /**
     * @return BelongsTo<HseIncident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(HseIncident::class, 'hse_incident_id');
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
