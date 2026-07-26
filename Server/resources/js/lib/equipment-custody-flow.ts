import { CheckoutState } from '@/types/enums';
import type { EquipmentByToken } from '@/types/equipment';

export type ScanCustodyFlow = 'checkout' | 'return';

/**
 * Decide checkout vs return after a successful equipment QR lookup (DOC-13).
 */
export function resolveScanCustodyFlow(
    data: Pick<EquipmentByToken, 'open_checkout' | 'checkout_state'>,
): ScanCustodyFlow {
    const hasOpen =
        data.open_checkout !== null ||
        data.checkout_state === CheckoutState.CheckedOut ||
        data.checkout_state === CheckoutState.OverdueReturn;

    if (hasOpen && data.open_checkout) {
        return 'return';
    }

    return 'checkout';
}
