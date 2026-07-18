<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Carbon\CarbonInterface;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property AuditEvent $event
 * @property int|null $user_id
 * @property string|null $route
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property CarbonInterface $occurred_at
 */
final class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'route',
        'occurred_at',
        'event_type',
        'payload',
        'ip',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (AuditLog $auditLog): void {
            $auditLog->occurred_at ??= now();
        });
        static::updating(static function (): never {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function setEventTypeAttribute(string $value): void
    {
        $event = match ($value) {
            'report_published' => AuditEvent::Published,
            'ppe_reviewed' => AuditEvent::Updated,
            default => AuditEvent::tryFrom($value) ?? AuditEvent::Updated,
        };
        $this->attributes['event'] = $event->value;
        $this->attributes['event_type'] = $value;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function setPayloadAttribute(?array $value): void
    {
        $this->attributes['new_values'] = $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
        $this->attributes['payload'] = $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
        if (! isset($this->attributes['description']) && isset($value['target']) && is_string($value['target'])) {
            $this->attributes['description'] = ucfirst(str_replace('_', ' ', $value['target'])).'.';
        }
    }

    public function setIpAttribute(?string $value): void
    {
        $this->attributes['ip_address'] = $value;
        $this->attributes['ip'] = $value;
    }
}
