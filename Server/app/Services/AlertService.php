<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Events\AlertRaised;
use App\Events\AlertUpdated;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AlertPolicy;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AlertService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function raise(
        AlertType $type,
        ?AlertSeverity $severity = null,
        string $title = '',
        array $payload = [],
        ?Model $source = null,
        ?bool $audible = null,
        ?string $dedupeKey = null,
    ): Alert {
        $defaults = AlertPolicy::defaults($type);
        $severity ??= $defaults['severity'];
        $audible ??= $defaults['audible'];

        if (! (bool) $this->settings->get('alert.audible_enabled', true)) {
            $audible = false;
        }

        if ($defaults['suggested_action'] !== null && ! isset($payload['suggested_action'])) {
            $payload['suggested_action'] = $defaults['suggested_action'];
        }

        if ($title === '') {
            $title = $type->label();
        }

        if ($dedupeKey !== null) {
            /** @var Alert|null $existing */
            $existing = Alert::query()
                ->where('dedupe_key', $dedupeKey)
                ->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'occurrences' => $existing->occurrences + 1,
                    'raised_at' => now(),
                    'payload' => array_merge($existing->payload ?? [], $payload),
                    'title' => $title,
                    'severity' => $severity,
                    'audible' => $audible,
                ])->save();

                $fresh = $existing->fresh() ?? $existing;
                broadcast(new AlertUpdated($fresh));

                return $fresh;
            }
        }

        $alert = Alert::query()->create([
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'payload' => $payload,
            'status' => AlertStatus::Open,
            'raised_at' => now(),
            'audible' => $audible,
            'dedupe_key' => $dedupeKey,
            'occurrences' => 1,
            'alertable_type' => $source?->getMorphClass(),
            'alertable_id' => $source?->getKey(),
        ]);

        broadcast(new AlertRaised($alert));

        return $alert;
    }

    public function acknowledge(Alert $alert, User $user): Alert
    {
        if ($alert->status === AlertStatus::Resolved) {
            throw new HttpException(409, 'Resolved alerts cannot be acknowledged.');
        }

        if ($alert->status === AlertStatus::Acknowledged) {
            return $alert;
        }

        $alert->forceFill([
            'status' => AlertStatus::Acknowledged,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ])->save();

        AuditLog::query()->create([
            'event_type' => 'acknowledged',
            'user_id' => $user->id,
            'route' => request()->path(),
            'payload' => [
                'target' => 'alert',
                'alert_id' => $alert->id,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        $fresh = $alert->fresh() ?? $alert;
        broadcast(new AlertUpdated($fresh));

        return $fresh;
    }

    public function resolve(Alert $alert, ?string $note = null): Alert
    {
        if ($alert->status === AlertStatus::Resolved) {
            return $alert;
        }

        $payload = $alert->payload ?? [];
        if ($note !== null) {
            $payload['resolve_note'] = $note;
        }

        $alert->forceFill([
            'status' => AlertStatus::Resolved,
            'resolved_at' => now(),
            'payload' => $payload,
        ])->save();

        if (auth()->id() !== null) {
            AuditLog::query()->create([
                'event_type' => 'config_changed',
                'user_id' => auth()->id(),
                'route' => request()->path(),
                'payload' => [
                    'target' => 'alert_resolve',
                    'alert_id' => $alert->id,
                    'note' => $note,
                ],
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        $fresh = $alert->fresh() ?? $alert;
        broadcast(new AlertUpdated($fresh));

        return $fresh;
    }

    public function resolveByDedupeKey(string $key): ?Alert
    {
        /** @var Alert|null $alert */
        $alert = Alert::query()
            ->where('dedupe_key', $key)
            ->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value])
            ->latest('id')
            ->first();

        if ($alert === null) {
            return null;
        }

        return $this->resolve($alert);
    }
}
