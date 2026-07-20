<?php

namespace App\Models;

use App\Enums\PermitStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $permit_number
 * @property int $permit_type_id
 * @property int|null $work_order_id
 * @property int|null $zone_id
 * @property string $task_description
 * @property int $receiver_id
 * @property int|null $issuer_id
 * @property int|null $approver_id
 * @property PermitStatus $status
 * @property int $renewal_count
 * @property bool $is_extended
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property array<string, mixed>|null $checklist
 * @property array<string, mixed>|null $controls
 * @property bool $gas_test_required
 * @property Carbon|null $joint_inspection_at
 * @property int|null $joint_inspection_by_issuer
 * @property int|null $joint_inspection_by_receiver
 * @property Carbon|null $issued_at
 * @property Carbon|null $closed_at
 * @property string|null $close_note
 * @property string|null $cancel_reason
 * @property string $source
 * @property int|null $created_by
 */
final class Permit extends Model
{
    use Auditable, HasCreatedBy, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PermitStatus::class,
            'is_extended' => 'boolean',
            'gas_test_required' => 'boolean',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'joint_inspection_at' => 'datetime',
            'issued_at' => 'datetime',
            'closed_at' => 'datetime',
            'checklist' => 'array',
            'controls' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PermitType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PermitType::class, 'permit_type_id');
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return HasMany<PermitPersonnel, $this>
     */
    public function personnel(): HasMany
    {
        return $this->hasMany(PermitPersonnel::class);
    }

    /**
     * @return HasMany<PermitGasTest, $this>
     */
    public function gasTests(): HasMany
    {
        return $this->hasMany(PermitGasTest::class);
    }

    /**
     * @return HasMany<PermitApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(PermitApproval::class);
    }

    /**
     * @return HasMany<PermitEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PermitEvent::class);
    }

    public function hasJointInspectionComplete(): bool
    {
        return $this->joint_inspection_by_issuer !== null
            && $this->joint_inspection_by_receiver !== null;
    }

    public function needsApprover(): bool
    {
        return $this->is_extended || ($this->type?->requires_approver ?? false);
    }
}
