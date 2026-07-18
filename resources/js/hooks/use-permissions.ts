import { useAuth } from '@/hooks/use-auth';
import type { Permission } from '@/types/enums';

export function usePermissions(): {
    permissions: string[];
    can: (permission: Permission | string) => boolean;
    canAny: (permissions: Array<Permission | string>) => boolean;
} {
    const { user } = useAuth();
    const permissions = user?.permissions ?? [];

    return {
        permissions,
        can: (permission) => permissions.includes(permission),
        canAny: (list) =>
            list.some((permission) => permissions.includes(permission)),
    };
}
