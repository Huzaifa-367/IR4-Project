import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useReverbChannel } from '@/hooks/use-reverb-channel';

const useEchoMock = vi.fn();
const useConnectionStatusMock = vi.fn();
const useAuthMock = vi.fn();
const useSharedSettingsMock = vi.fn();

vi.mock('@laravel/echo-react', () => ({
    useEcho: (...args: unknown[]) => useEchoMock(...args),
    useConnectionStatus: () => useConnectionStatusMock(),
}));

vi.mock('@/hooks/use-auth', () => ({
    useAuth: () => useAuthMock(),
    useSharedSettings: () => useSharedSettingsMock(),
}));

describe('useReverbChannel poll fallback', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        useAuthMock.mockReturnValue({ isAuthenticated: true });
        useSharedSettingsMock.mockReturnValue({
            poll_fallback_seconds: 12,
        });
        useConnectionStatusMock.mockReturnValue('disconnected');
        useEchoMock.mockImplementation(() => undefined);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('polls snapshotUrl on mount and on poll_fallback_seconds while offline', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ headcount: 3 }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const onSnapshot = vi.fn();

        renderHook(() =>
            useReverbChannel({
                channel: 'dashboard',
                events: ['HeadcountUpdated'],
                onEvent: vi.fn(),
                snapshotUrl: '/api/dashboard/summary',
                onSnapshot,
            }),
        );

        await act(async () => {
            await Promise.resolve();
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(onSnapshot).toHaveBeenCalledWith({ headcount: 3 });

        await act(async () => {
            await vi.advanceTimersByTimeAsync(12_000);
        });

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('stops polling when the socket is live', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ ok: true }),
        });
        vi.stubGlobal('fetch', fetchMock);

        useConnectionStatusMock.mockReturnValue('connected');

        renderHook(() =>
            useReverbChannel({
                channel: 'dashboard',
                events: ['HeadcountUpdated'],
                onEvent: vi.fn(),
                snapshotUrl: '/api/dashboard/summary',
                onSnapshot: vi.fn(),
            }),
        );

        await act(async () => {
            await Promise.resolve();
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(30_000);
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});
