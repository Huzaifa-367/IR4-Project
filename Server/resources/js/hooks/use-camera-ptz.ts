import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';

type MoveVector = {
    pan: number;
    tilt: number;
    zoom: number;
};

type CommandPayload =
    | { action: 'move'; pan: number; tilt: number; zoom: number }
    | { action: 'stop' };

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function readErrorMessage(response: Response): Promise<string> {
    try {
        const json = (await response.json()) as {
            error?: { message?: string };
        };

        if (
            typeof json.error?.message === 'string' &&
            json.error.message !== ''
        ) {
            return json.error.message;
        }
    } catch {
        // Fall through to generic message.
    }

    return 'PTZ command failed.';
}

export function useCameraPtz(ptzUrl: string, enabled: boolean) {
    const [activeKey, setActiveKey] = useState<string | null>(null);
    const [isBusy, setIsBusy] = useState(false);
    const lastErrorAtRef = useRef(0);
    const activeTimeoutRef = useRef<number | null>(null);

    const notifyError = useCallback((message: string): void => {
        const now = Date.now();

        if (now - lastErrorAtRef.current < 2_000) {
            return;
        }

        lastErrorAtRef.current = now;
        toast.error(message);
    }, []);

    const postCommand = useCallback(
        async (payload: CommandPayload): Promise<boolean> => {
            try {
                const response = await fetch(ptzUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': readCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    notifyError(await readErrorMessage(response));

                    return false;
                }

                return true;
            } catch {
                notifyError(
                    'Cannot reach the camera — check pole network and try again.',
                );

                return false;
            }
        },
        [notifyError, ptzUrl],
    );

    const flashActiveKey = useCallback((key: string): void => {
        if (activeTimeoutRef.current !== null) {
            window.clearTimeout(activeTimeoutRef.current);
        }

        setActiveKey(key);
        activeTimeoutRef.current = window.setTimeout(() => {
            setActiveKey(null);
            activeTimeoutRef.current = null;
        }, 180);
    }, []);

    const nudge = useCallback(
        async (key: string, pan: number, tilt: number, zoom = 0): Promise<void> => {
            if (!enabled || isBusy) {
                return;
            }

            setIsBusy(true);
            flashActiveKey(key);

            await postCommand({ action: 'move', pan, tilt, zoom });
            setIsBusy(false);
        },
        [enabled, flashActiveKey, isBusy, postCommand],
    );

    const stop = useCallback(async (): Promise<void> => {
        if (!enabled || isBusy) {
            return;
        }

        setIsBusy(true);
        setActiveKey(null);

        if (activeTimeoutRef.current !== null) {
            window.clearTimeout(activeTimeoutRef.current);
            activeTimeoutRef.current = null;
        }

        await postCommand({ action: 'stop' });
        setIsBusy(false);
    }, [enabled, isBusy, postCommand]);

    return {
        activeKey,
        isBusy,
        nudge,
        stop,
    };
}
