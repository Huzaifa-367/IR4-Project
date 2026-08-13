import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAuth, useSettingsTimeoutMinutes } from '@/hooks/use-auth';

const ACTIVITY_EVENTS = [
    'mousemove',
    'keydown',
    'click',
    'scroll',
    'touchstart',
] as const;

function postHeartbeat(): Promise<Response> {
    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    return fetch('/session/heartbeat', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
    });
}

/**
 * Client idle timeout (DOC-02 §5.2). Server EnforceIdleTimeout is authoritative.
 * Returns a warning dialog element to render in the layout.
 */
export function useIdleLogout(): React.ReactNode {
    const { isAuthenticated } = useAuth();
    const timeoutMinutes = useSettingsTimeoutMinutes();
    const timeoutMs = Math.max(1, timeoutMinutes) * 60 * 1000;
    const warningMs = 60_000;
    const [secondsLeft, setSecondsLeft] = useState<number | null>(null);
    const lastActivity = useRef(0);

    const staySignedIn = useCallback((): void => {
        void postHeartbeat().then(() => {
            lastActivity.current = Date.now();
            setSecondsLeft(null);
        });
    }, []);

    useEffect(() => {
        if (!isAuthenticated) {
            return;
        }

        lastActivity.current = Date.now();

        const mark = (): void => {
            lastActivity.current = Date.now();
            setSecondsLeft(null);
        };

        let throttleUntil = 0;
        const onActivity = (): void => {
            const now = Date.now();

            if (now < throttleUntil) {
                return;
            }

            throttleUntil = now + 1000;
            mark();
        };

        for (const event of ACTIVITY_EVENTS) {
            window.addEventListener(event, onActivity, { passive: true });
        }

        const tick = window.setInterval(() => {
            const remaining = timeoutMs - (Date.now() - lastActivity.current);

            if (remaining <= 0) {
                router.get('/login?timeout=1');

                return;
            }

            if (remaining <= warningMs) {
                setSecondsLeft(Math.ceil(remaining / 1000));
            }
        }, 1000);

        return () => {
            for (const event of ACTIVITY_EVENTS) {
                window.removeEventListener(event, onActivity);
            }

            window.clearInterval(tick);
        };
    }, [isAuthenticated, timeoutMs]);

    return (
        <Dialog open={secondsLeft !== null}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Still there?</DialogTitle>
                    <DialogDescription>
                        You&apos;ll be signed out for inactivity in{' '}
                        {secondsLeft !== null
                            ? `0:${String(secondsLeft).padStart(2, '0')}`
                            : '…'}
                        .
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" onClick={staySignedIn}>
                        Stay signed in
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
