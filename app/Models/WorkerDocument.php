<?php

namespace App\Models;

use App\Enums\WorkerDocumentVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $worker_id
 * @property int $worker_document_type_id
 * @property string|null $document_number
 * @property string|null $issuing_body
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property string|null $file_path
 * @property WorkerDocumentVerificationStatus $verification_status
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $notes
 * @property int|null $uploaded_by
 */
final class WorkerDocument extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'verification_status' => WorkerDocumentVerificationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * @return BelongsTo<WorkerDocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(WorkerDocumentType::class, 'worker_document_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isVerifiedAndValid(): bool
    {
        if ($this->verification_status !== WorkerDocumentVerificationStatus::Verified) {
            return false;
        }

        return ! $this->isExpired();
    }
}
