<?php

namespace App\Models;

use Database\Factories\WorkerImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $created_by
 * @property string $original_filename
 * @property string $stored_path
 * @property string $status
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class WorkerImport extends Model
{
    /** @use HasFactory<WorkerImportFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
