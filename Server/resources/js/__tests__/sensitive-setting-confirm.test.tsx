import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SensitiveSettingConfirm } from '@/components/ir4/settings/sensitive-setting-confirm';
import type { SettingSchema } from '@/types/settings';

const setting: SettingSchema = {
    key: 'auth.session_timeout_minutes',
    label: 'Session timeout',
    description: 'Idle logout threshold.',
    type: 'int',
    unit: 'minutes',
    value: 15,
    default: 15,
    min: 5,
    max: 480,
    options: null,
    requires_confirm: true,
    editable: true,
    permission: 'manage-settings-auth',
    updated_at: null,
    updated_by: null,
};

describe('SensitiveSettingConfirm', () => {
    it('shows current and new values and calls confirm handlers', async () => {
        const user = userEvent.setup();
        const onCancel = vi.fn();
        const onConfirm = vi.fn();

        render(
            <SensitiveSettingConfirm
                open
                setting={setting}
                nextValue={30}
                onCancel={onCancel}
                onConfirm={onConfirm}
            />,
        );

        expect(screen.getByText('Confirm sensitive change')).toBeInTheDocument();
        expect(screen.getByText('Session timeout')).toBeInTheDocument();
        expect(screen.getByText('auth.session_timeout_minutes')).toBeInTheDocument();
        expect(screen.getByText('15')).toBeInTheDocument();
        expect(screen.getByText('30')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Confirm change' }));
        expect(onConfirm).toHaveBeenCalledTimes(1);

        await user.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(onCancel).toHaveBeenCalledTimes(1);
    });

    it('renders nothing when setting is null', () => {
        const { container } = render(
            <SensitiveSettingConfirm
                open
                setting={null}
                nextValue={30}
                onCancel={vi.fn()}
                onConfirm={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
