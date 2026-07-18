<?php

namespace App\Services;

use App\Enums\CheckoutState;
use App\Enums\EquipmentStatus;
use App\Enums\ReturnStatus;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EquipmentCheckoutService
{
    /**
     * @param  array{
     *     worker_id: int,
     *     reason?: string|null,
     *     zone_id?: int|null,
     *     expected_return_at?: string|null,
     *     condition_out?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function checkout(Equipment $equipment, array $data, User $actor): EquipmentCheckout
    {
        return DB::transaction(function () use ($equipment, $data, $actor): EquipmentCheckout {
            /** @var Equipment $locked */
            $locked = Equipment::query()->whereKey($equipment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_checkoutable) {
                throw ValidationException::withMessages([
                    'equipment' => ['This equipment is not checkoutable.'],
                ]);
            }

            if ($locked->status !== EquipmentStatus::InService) {
                throw ValidationException::withMessages([
                    'equipment' => ['Only in-service equipment can be checked out.'],
                ]);
            }

            if ($locked->status === EquipmentStatus::Retired) {
                throw ValidationException::withMessages([
                    'equipment' => ['Retired equipment cannot be checked out.'],
                ]);
            }

            $open = EquipmentCheckout::query()
                ->where('equipment_id', $locked->id)
                ->whereNull('returned_at')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw new HttpException(409, 'Equipment already has an open checkout.');
            }

            $worker = Worker::query()->findOrFail((int) $data['worker_id']);
            if (! $worker->is_active) {
                throw ValidationException::withMessages([
                    'worker_id' => ['Worker is not active.'],
                ]);
            }

            $checkout = EquipmentCheckout::query()->create([
                'equipment_id' => $locked->id,
                'worker_id' => $worker->id,
                'checked_out_at' => now(),
                'checked_out_by' => $actor->id,
                'reason' => $data['reason'] ?? null,
                'zone_id' => $data['zone_id'] ?? null,
                'expected_return_at' => $data['expected_return_at'] ?? null,
                'condition_out' => $data['condition_out'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit('created', [
                'target' => 'equipment_checkout',
                'equipment_id' => $locked->id,
                'checkout_id' => $checkout->id,
                'worker_id' => $worker->id,
            ]);

            return $checkout->fresh(['worker', 'zone']) ?? $checkout;
        });
    }

    /**
     * @param  array{
     *     return_status?: string|ReturnStatus|null,
     *     return_reason?: string|null,
     *     condition_in?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function returnItem(EquipmentCheckout $checkout, array $data, User $actor): EquipmentCheckout
    {
        return $this->returnCheckout($checkout, $data, $actor);
    }

    /**
     * @param  array{
     *     return_status?: string|ReturnStatus|null,
     *     return_reason?: string|null,
     *     condition_in?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function returnCheckout(EquipmentCheckout $checkout, array $data, User $actor): EquipmentCheckout
    {
        return DB::transaction(function () use ($checkout, $data, $actor): EquipmentCheckout {
            /** @var EquipmentCheckout $locked */
            $locked = EquipmentCheckout::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new HttpException(409, 'Checkout is already returned.');
            }

            $returnStatus = $data['return_status'] ?? null;
            if ($returnStatus instanceof ReturnStatus) {
                $returnStatus = $returnStatus->value;
            }
            if ($returnStatus !== null && ReturnStatus::tryFrom((string) $returnStatus) === null) {
                throw ValidationException::withMessages([
                    'return_status' => ['return_status must be ok, damaged, or needs_service.'],
                ]);
            }

            $locked->forceFill([
                'returned_at' => now(),
                'returned_to' => $actor->id,
                'return_status' => $returnStatus,
                'return_reason' => $data['return_reason'] ?? null,
                'condition_in' => $data['condition_in'] ?? null,
                'notes' => $data['notes'] ?? $locked->notes,
            ])->save();

            $this->audit('updated', [
                'target' => 'equipment_return',
                'equipment_id' => $locked->equipment_id,
                'checkout_id' => $locked->id,
                'return_status' => $returnStatus,
            ]);

            return $locked->fresh(['worker', 'zone', 'equipment']) ?? $locked;
        });
    }

    public function stateFor(Equipment $equipment): CheckoutState
    {
        $open = $equipment->relationLoaded('openCheckout')
            ? $equipment->openCheckout
            : $equipment->openCheckout()->first();

        return $this->state($open);
    }

    public function state(?EquipmentCheckout $open): CheckoutState
    {
        if ($open === null || ! $open->isOpen()) {
            return CheckoutState::Available;
        }

        if ($open->expected_return_at !== null && $open->expected_return_at->isPast()) {
            return CheckoutState::OverdueReturn;
        }

        return CheckoutState::CheckedOut;
    }

    public function countOverdueReturns(): int
    {
        return EquipmentCheckout::query()
            ->whereNull('returned_at')
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<', now())
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overdueReturnPayloads(bool $canSeeIdentity = false): array
    {
        return EquipmentCheckout::query()
            ->with(['equipment', 'worker', 'zone'])
            ->whereNull('returned_at')
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<', now())
            ->orderBy('expected_return_at')
            ->get()
            ->map(fn (EquipmentCheckout $c): array => $this->checkoutPayload($c, $canSeeIdentity))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openCheckoutPayload(?EquipmentCheckout $checkout, bool $canSeeIdentity = false): ?array
    {
        if ($checkout === null) {
            return null;
        }

        return $this->checkoutPayload($checkout, $canSeeIdentity);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutPayload(EquipmentCheckout $checkout, bool $canSeeIdentity = false): array
    {
        $checkout->loadMissing(['worker', 'zone', 'equipment']);

        $worker = $checkout->worker;
        $workerPayload = null;
        if ($worker !== null) {
            $workerPayload = [
                'id' => $worker->id,
                'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
                'contractor' => $worker->contractor,
                'role_title' => $worker->role_title,
                'worker_type' => $worker->worker_type->value,
            ];
        }

        return [
            'id' => $checkout->id,
            'equipment_id' => $checkout->equipment_id,
            'equipment_code' => $checkout->equipment?->equipment_code,
            'equipment_name' => $checkout->equipment?->name,
            'worker_id' => $checkout->worker_id,
            'worker' => $workerPayload,
            'checked_out_at' => $checkout->checked_out_at?->toIso8601String(),
            'checked_out_by' => $checkout->checked_out_by,
            'reason' => $checkout->reason,
            'zone_id' => $checkout->zone_id,
            'zone' => $checkout->zone !== null ? [
                'id' => $checkout->zone->id,
                'name' => $checkout->zone->name,
            ] : null,
            'expected_return_at' => $checkout->expected_return_at?->toIso8601String(),
            'returned_at' => $checkout->returned_at?->toIso8601String(),
            'returned_to' => $checkout->returned_to,
            'condition_out' => $checkout->condition_out,
            'condition_in' => $checkout->condition_in,
            'return_status' => $checkout->return_status?->value,
            'return_reason' => $checkout->return_reason,
            'notes' => $checkout->notes,
            'checkout_state' => $this->state($checkout->isOpen() ? $checkout : null)->value,
            'is_overdue_return' => $checkout->isOpen()
                && $checkout->expected_return_at !== null
                && $checkout->expected_return_at->isPast(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $eventType, array $payload): void
    {
        AuditLog::query()->create([
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => $payload,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
