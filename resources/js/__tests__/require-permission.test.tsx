import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { RequirePermission } from '@/components/ir4/require-permission';

vi.mock('@/hooks/use-auth', () => ({
    useAuth: () => ({
        user: { permissions: ['create-equipment'] },
    }),
}));

vi.mock('@/hooks/use-permissions', () => ({
    usePermissions: () => ({
        permissions: ['create-equipment'],
        can: (permission: string) => permission === 'create-equipment',
        canAny: (list: string[]) => list.includes('create-equipment'),
    }),
}));

describe('RequirePermission', () => {
    it('renders children when the user has the permission', () => {
        render(
            <RequirePermission permission="create-equipment">
                <span>Allowed</span>
            </RequirePermission>,
        );

        expect(screen.getByText('Allowed')).toBeInTheDocument();
    });

    it('renders fallback when the user lacks the permission', () => {
        render(
            <RequirePermission
                permission="delete-equipment"
                fallback={<span>Denied</span>}
            >
                <span>Allowed</span>
            </RequirePermission>,
        );

        expect(screen.queryByText('Allowed')).not.toBeInTheDocument();
        expect(screen.getByText('Denied')).toBeInTheDocument();
    });
});
