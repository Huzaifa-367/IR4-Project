import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Minus,
    Plus,
    Square,
} from 'lucide-react';
import { useCallback, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const PTZ_SPEED = 45;

type Props = {
    cameraUuid: string;
    ptzUrl: string;
    className?: string;
};

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export function LiveCameraPtzControls({
    cameraUuid,
    ptzUrl,
    className,
}: Props) {
    const activeMoveRef = useRef<string | null>(null);

    const sendCommand = useCallback(
        async (payload: {
            action: 'move' | 'stop';
            pan?: number;
            tilt?: number;
            zoom?: number;
        }): Promise<void> => {
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
                    return;
                }
            } catch {
                // Network errors are surfaced only when the operator retries.
            }
        },
        [ptzUrl],
    );

    const stopMove = useCallback(async (): Promise<void> => {
        if (activeMoveRef.current === null) {
            return;
        }

        activeMoveRef.current = null;
        await sendCommand({ action: 'stop' });
    }, [sendCommand]);

    const startMove = useCallback(
        async (key: string, pan: number, tilt: number, zoom = 0): Promise<void> => {
            activeMoveRef.current = key;
            await sendCommand({
                action: 'move',
                pan,
                tilt,
                zoom,
            });
        },
        [sendCommand],
    );

    const bindMove = useCallback(
        (key: string, pan: number, tilt: number, zoom = 0) => ({
            onPointerDown: (event: React.PointerEvent<HTMLButtonElement>) => {
                event.preventDefault();
                event.currentTarget.setPointerCapture(event.pointerId);
                void startMove(key, pan, tilt, zoom);
            },
            onPointerUp: () => {
                void stopMove();
            },
            onPointerCancel: () => {
                void stopMove();
            },
            onPointerLeave: (event: React.PointerEvent<HTMLButtonElement>) => {
                if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                    return;
                }

                void stopMove();
            },
        }),
        [startMove, stopMove],
    );

    return (
        <div
            className={cn(
                'pointer-events-auto rounded-[var(--radius)] border border-border/60 bg-black/70 p-3 text-text shadow-lg backdrop-blur-sm',
                className,
            )}
            data-camera-uuid={cameraUuid}
        >
            <div className="mb-2 text-xs font-medium uppercase tracking-wide text-text-muted">
                PTZ controls
            </div>
            <div className="flex items-end gap-4">
                <div className="grid grid-cols-3 gap-1">
                    <div />
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Tilt up"
                        {...bindMove('up', 0, PTZ_SPEED)}
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <div />
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Pan left"
                        {...bindMove('left', -PTZ_SPEED, 0)}
                    >
                        <ArrowLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Stop PTZ"
                        onPointerDown={(event) => {
                            event.preventDefault();
                            void stopMove();
                        }}
                    >
                        <Square className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Pan right"
                        {...bindMove('right', PTZ_SPEED, 0)}
                    >
                        <ArrowRight className="size-4" />
                    </Button>
                    <div />
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Tilt down"
                        {...bindMove('down', 0, -PTZ_SPEED)}
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <div />
                </div>
                <div className="flex flex-col gap-1">
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Zoom in"
                        {...bindMove('zoom-in', 0, 0, PTZ_SPEED)}
                    >
                        <Plus className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Zoom out"
                        {...bindMove('zoom-out', 0, 0, -PTZ_SPEED)}
                    >
                        <Minus className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
