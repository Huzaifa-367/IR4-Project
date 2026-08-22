import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

const PTZ_KEEPALIVE_MS = 500;

type MoveVector = {
    pan: number;
    tilt: number;
    zoom: number;
};

type CommandPayload =
    | { action: 'move'; pan: number; tilt: number; zoom: number }
    | { action: 'stop' };

type SendOptions = {
    keepalive?: boolean;
    silent?: boolean;
};

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
    const sessionRef = useRef<{
        key: string;
        vector: MoveVector;
        keepaliveId: number | null;
    } | null>(null);
    const requestGenerationRef = useRef(0);
    const lastErrorAtRef = useRef(0);

    const notifyError = useCallback(
        (message: string, silent: boolean): void => {
            if (silent) {
                return;
            }

            const now = Date.now();

            if (now - lastErrorAtRef.current < 2_000) {
                return;
            }

            lastErrorAtRef.current = now;
            toast.error(message);
        },
        [],
    );

    const sendCommand = useCallback(
        async (
            payload: CommandPayload,
            options: SendOptions = {},
        ): Promise<boolean> => {
            const generation = ++requestGenerationRef.current;

            if (!options.keepalive) {
                setIsBusy(true);
            }

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
                    keepalive: options.keepalive === true,
                    body: JSON.stringify(payload),
                });

                if (generation !== requestGenerationRef.current) {
                    return false;
                }

                if (!response.ok) {
                    notifyError(
                        await readErrorMessage(response),
                        options.silent === true,
                    );

                    return false;
                }

                return true;
            } catch {
                notifyError(
                    'Cannot reach the camera — check pole network and try again.',
                    options.silent === true,
                );

                return false;
            } finally {
                if (
                    !options.keepalive &&
                    generation === requestGenerationRef.current
                ) {
                    setIsBusy(false);
                }
            }
        },
        [notifyError, ptzUrl],
    );

    const clearKeepalive = useCallback((): void => {
        const session = sessionRef.current;

        if (
            session?.keepaliveId !== null &&
            session?.keepaliveId !== undefined
        ) {
            window.clearInterval(session.keepaliveId);
        }
    }, []);

    const stopMove = useCallback(
        async (options: SendOptions = {}): Promise<void> => {
            if (sessionRef.current === null) {
                return;
            }

            clearKeepalive();
            sessionRef.current = null;
            setActiveKey(null);
            await sendCommand({ action: 'stop' }, options);
        },
        [clearKeepalive, sendCommand],
    );

    const startMove = useCallback(
        (key: string, pan: number, tilt: number, zoom = 0): void => {
            if (!enabled) {
                return;
            }

            const vector: MoveVector = { pan, tilt, zoom };
            const current = sessionRef.current;

            if (current?.key === key) {
                return;
            }

            void (async (): Promise<void> => {
                if (current !== null) {
                    await stopMove({ silent: true, keepalive: true });
                }

                sessionRef.current = {
                    key,
                    vector,
                    keepaliveId: window.setInterval(() => {
                        if (sessionRef.current?.key !== key) {
                            return;
                        }

                        void sendCommand(
                            { action: 'move', ...vector },
                            { silent: true, keepalive: false },
                        );
                    }, PTZ_KEEPALIVE_MS),
                };
                setActiveKey(key);

                const ok = await sendCommand({
                    action: 'move',
                    ...vector,
                });

                if (!ok && sessionRef.current?.key === key) {
                    clearKeepalive();
                    sessionRef.current = null;
                    setActiveKey(null);
                }
            })();
        },
        [clearKeepalive, enabled, sendCommand, stopMove],
    );

    useEffect(() => {
        if (enabled) {
            return;
        }

        void stopMove({ silent: true, keepalive: true });
    }, [enabled, stopMove]);

    useEffect(() => {
        const onVisibilityChange = (): void => {
            if (document.hidden) {
                void stopMove({ keepalive: true, silent: true });
            }
        };

        const onPageHide = (): void => {
            void stopMove({ keepalive: true, silent: true });
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', onPageHide);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.removeEventListener('pagehide', onPageHide);
            void stopMove({ keepalive: true, silent: true });
        };
    }, [stopMove]);

    useEffect(() => {
        if (activeKey === null) {
            return;
        }

        const onPointerRelease = (): void => {
            void stopMove({ keepalive: true, silent: true });
        };

        window.addEventListener('pointerup', onPointerRelease);
        window.addEventListener('pointercancel', onPointerRelease);

        return () => {
            window.removeEventListener('pointerup', onPointerRelease);
            window.removeEventListener('pointercancel', onPointerRelease);
        };
    }, [activeKey, stopMove]);

    return {
        activeKey,
        isBusy,
        startMove,
        stopMove,
    };
}
