import { describe, expect, it } from 'vitest';
import { resolveScanCustodyFlow } from '@/lib/equipment-custody-flow';
import { CheckoutState } from '@/types/enums';
import type { EquipmentByToken } from '@/types/equipment';

function makeLookup(
    patch: Partial<Pick<EquipmentByToken, 'open_checkout' | 'checkout_state'>>,
): Pick<EquipmentByToken, 'open_checkout' | 'checkout_state'> {
    return {
        open_checkout: null,
        checkout_state: CheckoutState.Available,
        ...patch,
    };
}

describe('resolveScanCustodyFlow', () => {
    it('routes to checkout when equipment is available', () => {
        expect(resolveScanCustodyFlow(makeLookup({}))).toBe('checkout');
    });

    it('routes to return when an open checkout exists', () => {
        expect(
            resolveScanCustodyFlow(
                makeLookup({
                    checkout_state: CheckoutState.CheckedOut,
                    open_checkout: {
                        id: 1,
                        equipment_id: 10,
                        worker_id: 4,
                        worker: { id: 4, name: 'Worker #4' },
                        checked_out_at: '2026-07-18T10:00:00Z',
                        checked_out_by: null,
                        checked_out_by_user: null,
                        reason: null,
                        zone_id: null,
                        zone: null,
                        expected_return_at: null,
                        returned_at: null,
                        returned_to: null,
                        returned_to_user: null,
                        condition_out: null,
                        condition_in: null,
                        return_status: null,
                        return_reason: null,
                        notes: null,
                    },
                }),
            ),
        ).toBe('return');
    });

    it('routes to checkout when state is checked out but open_checkout is missing', () => {
        expect(
            resolveScanCustodyFlow(
                makeLookup({
                    checkout_state: CheckoutState.CheckedOut,
                    open_checkout: null,
                }),
            ),
        ).toBe('checkout');
    });

    it('routes to return for overdue equipment with an open checkout', () => {
        expect(
            resolveScanCustodyFlow(
                makeLookup({
                    checkout_state: CheckoutState.OverdueReturn,
                    open_checkout: {
                        id: 2,
                        equipment_id: 11,
                        worker_id: 7,
                        worker: { id: 7, name: 'Worker #7' },
                        checked_out_at: '2026-07-10T10:00:00Z',
                        checked_out_by: null,
                        checked_out_by_user: null,
                        reason: null,
                        zone_id: null,
                        zone: null,
                        expected_return_at: null,
                        returned_at: null,
                        returned_to: null,
                        returned_to_user: null,
                        condition_out: null,
                        condition_in: null,
                        return_status: null,
                        return_reason: null,
                        notes: null,
                        is_overdue_return: true,
                    },
                }),
            ),
        ).toBe('return');
    });
});
