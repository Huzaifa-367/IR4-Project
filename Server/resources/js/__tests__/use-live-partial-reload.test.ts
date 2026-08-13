import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useLivePartialReload } from '@/hooks/use-live-partial-reload';

const useReverbChannelMock = vi.fn();
const reloadMock = vi.fn();

vi.mock('@/hooks/use-reverb-channel', () => ({
    useReverbChannel: (...args: unknown[]) => useReverbChannelMock(...args),
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        reload: (...args: unknown[]) => reloadMock(...args),
    },
}));

describe('useLivePartialReload', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        reloadMock.mockReset();
        useReverbChannelMock.mockReset();
        useReverbChannelMock.mockImplementation(() => ({
            status: 'live',
            refresh: async () => undefined,
        }));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('reloads only named props when the event matches', () => {
        renderHook(() =>
            useLivePartialReload<{ permit: { uuid: string } }>({
                channel: 'permits',
                events: ['.PermitUpdated'],
                only: ['permit'],
                matches: (payload) => payload.permit.uuid === 'abc',
            }),
        );

        const onEvent = useReverbChannelMock.mock.calls[0][0]
            .onEvent as (payload: { permit: { uuid: string } }) => void;

        act(() => {
            onEvent({ permit: { uuid: 'abc' } });
        });

        expect(reloadMock).toHaveBeenCalledWith({
            only: ['permit'],
        });
    });

    it('ignores events for other records and throttles bursts', () => {
        renderHook(() =>
            useLivePartialReload<{ permit: { uuid: string } }>({
                channel: 'permits',
                events: ['.PermitUpdated'],
                only: ['permit'],
                matches: (payload) => payload.permit.uuid === 'abc',
                throttleMs: 1000,
            }),
        );

        const onEvent = useReverbChannelMock.mock.calls[0][0]
            .onEvent as (payload: { permit: { uuid: string } }) => void;

        act(() => {
            onEvent({ permit: { uuid: 'other' } });
            onEvent({ permit: { uuid: 'abc' } });
            onEvent({ permit: { uuid: 'abc' } });
        });

        expect(reloadMock).toHaveBeenCalledTimes(1);
    });
});
