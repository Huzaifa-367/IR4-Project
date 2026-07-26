<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    private const MASK = '••••';

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  list<string>  $maskedFields
     */
    public function record(
        AuditEvent $event,
        ?Model $auditable = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        array $maskedFields = [],
        ?string $route = null,
    ): AuditLog {
        $request = $this->getRequest();

        return AuditLog::query()->create([
            'user_id' => $user?->getKey() ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'old_values' => $this->maskValues($oldValues, $maskedFields),
            'new_values' => $this->maskValues($newValues, $maskedFields),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'route' => $route ?? $request?->route()?->getName(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @param  list<string>  $maskedFields
     * @return array<string, mixed>|null
     */
    private function maskValues(?array $values, array $maskedFields): ?array
    {
        if ($values === null) {
            return null;
        }

        $alwaysMasked = [
            'password',
            'remember_token',
            'api_token',
            'api_token_hash',
            'token',
            'token_hash',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];
        $mask = array_fill_keys(array_merge($alwaysMasked, $maskedFields), true);

        return collect($values)->mapWithKeys(
            static fn (mixed $value, string $key): array => [
                $key => isset($mask[$key]) ? self::MASK : $value,
            ],
        )->all();
    }

    private function getRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request();
    }
}
