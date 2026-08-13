import { router } from '@inertiajs/react';
import { useRef } from 'react';
import { useReverbChannel } from '@/hooks/use-reverb-channel';

type UseLivePartialReloadOptions<TPayload> = {
    channel: string;
    events: string[];
    only: string[];
    matches?: (payload: TPayload) => boolean;
    throttleMs?: number;
};

/**
 * Reverb signals that Inertia props are stale; Laravel remains the source of
 * truth via a targeted partial reload (no full-page visit). Inertia 3 reload
 * always keeps scroll and component state.
 */
export function useLivePartialReload<TPayload = unknown>({
    channel,
    events,
    only,
    matches,
    throttleMs = 1500,
}: UseLivePartialReloadOptions<TPayload>): void {
    const lastReloadAt = useRef(0);

    useReverbChannel<TPayload>({
        channel,
        events,
        onEvent: (payload) => {
            if (matches !== undefined && !matches(payload)) {
                return;
            }

            const now = Date.now();

            if (now - lastReloadAt.current < throttleMs) {
                return;
            }

            lastReloadAt.current = now;
            router.reload({ only });
        },
    });
}
