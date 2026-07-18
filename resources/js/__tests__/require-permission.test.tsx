import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { RequirePermission } from '@/components/ir4/require-permission';

vi.mock('@/hooks/use-permissions', () => ({
    usePermissions: vi.fn(),
}));

import { usePermissions } from '@/hooks/use-permissions';

const mockedUsePermissions = vi.mocked(usePermissions);

describe('RequirePermission', () => {
    it('renders children when the user has the permission', () => {
        mockedUsePermissions.mockReturnValue({
            permissions: ['manage-equipment'],
            can: (permission) => permission === 'manage-equipment',
            canAny: (list) => list.includes('manage-equipment'),
        });

        render(
            <RequirePermission permission="manage-equipment">
                <button type="button">Add equipment</button>
            </RequirePermission>,
        );

        expect(
            screen.getByRole('button', { name: 'Add equipment' }),
        ).toBeInTheDocument();
    });

    it('renders fallback when the user lacks the permission', () => {
        mockedUsePermissions.mockReturnValue({
            permissions: [],
            can: () => false,
            canAny: () => false,
        });

        render(
            <RequirePermission
                permission="manage-equipment"
                fallback={<p>Access denied</p>}
            >
                <button type="button">Add equipment</button>
            </RequirePermission>,
        );

        expect(screen.getByText('Access denied')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add equipment' }),
        ).not.toBeInTheDocument();
    });
});
