<?php

namespace App\Models;

use App\Enums\PermitApprovalAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_id
 * @property int $user_id
 * @property PermitApprovalAction $action
 * @property string|null $note
 * @property Carbon $signed_at
 */
final class PermitApproval extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PermitApprovalAction::class,
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Permit, $this>
     */
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
