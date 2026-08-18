import { describe, expect, it } from 'vitest';
import { pageDataChanged, pageDataFingerprint } from '@/lib/reload-on-history';

const base = {
    component: 'hse/lsr/show',
    url: '/lsr-violations/abc',
    props: {
        violation: { id: 1, status: 'open' },
        auth: { user: { id: 9 } },
        errors: {},
        flash: { success: 'ignored' },
        sidebarOpen: true,
    },
};

describe('pageDataFingerprint', () => {
    it('ignores flash, errors, and sidebar chrome', () => {
        const a = pageDataFingerprint(base);
        const b = pageDataFingerprint({
            ...base,
            props: {
                ...base.props,
                errors: { action_taken: 'required' },
                flash: null,
                sidebarOpen: false,
            },
        });

        expect(a).toBe(b);
    });

    it('detects domain payload changes', () => {
        expect(
            pageDataChanged(base, {
                ...base,
                props: {
                    ...base.props,
                    violation: { id: 1, status: 'closed' },
                },
            }),
        ).toBe(true);
    });

    it('treats identical domain data as unchanged', () => {
        expect(
            pageDataChanged(base, { ...base, props: { ...base.props } }),
        ).toBe(false);
    });
});
