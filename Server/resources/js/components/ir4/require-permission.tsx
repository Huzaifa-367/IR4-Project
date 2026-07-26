import type { ReactNode } from 'react';
import { usePermissions } from '@/hooks/use-permissions';
import type { Permission } from '@/types/enums';

type Props = {
    permission: Permission | string;
    children: ReactNode;
    fallback?: ReactNode;
};

/**
 * UX-only guard (DOC-03 §8.4). Backend always re-checks.
 */
export function RequirePermission({
    permission,
    children,
    fallback = null,
}: Props): ReactNode {
    const { can } = usePermissions();

    if (!can(permission)) {
        return fallback;
    }

    return children;
}
