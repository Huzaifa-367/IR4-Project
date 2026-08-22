import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Minus,
    Plus,
    Square,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCameraPtz } from '@/hooks/use-camera-ptz';
import { cn } from '@/lib/utils';

const PTZ_SPEED = 45;

type Props = {
    cameraUuid: string;
    cameraName: string;
    ptzUrl: string;
    enabled?: boolean;
    isOnline?: boolean;
    className?: string;
};

export function LiveCameraPtzControls({
    cameraUuid,
    cameraName,
    ptzUrl,
    enabled = true,
    isOnline = true,
    className,
}: Props) {
    const canOperate = enabled && isOnline;
    const { activeKey, startMove, stopMove } = useCameraPtz(
        ptzUrl,
        canOperate,
    );

    const handleStop = (): void => {
        if (!canOperate) {
            return;
        }

        void stopMove();
    };

    const bindMove = (key: string, pan: number, tilt: number, zoom = 0) => ({
        disabled: !canOperate,
        onPointerDown: (event: React.PointerEvent<HTMLButtonElement>) => {
            if (!canOperate) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.currentTarget.setPointerCapture(event.pointerId);
            startMove(key, pan, tilt, zoom);
        },
        onPointerUp: (event: React.PointerEvent<HTMLButtonElement>) => {
            event.preventDefault();
            event.stopPropagation();

            if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                event.currentTarget.releasePointerCapture(event.pointerId);
            }

            void stopMove({ keepalive: true, silent: true });
        },
        onPointerCancel: (event: React.PointerEvent<HTMLButtonElement>) => {
            event.preventDefault();
            void stopMove({ keepalive: true, silent: true });
        },
        onLostPointerCapture: () => {
            void stopMove({ keepalive: true, silent: true });
        },
    });

    return (
        <div
            className={cn(
                'pointer-events-auto touch-none rounded-[var(--radius)] border border-border/60 bg-black/75 p-3 text-text shadow-lg backdrop-blur-sm select-none',
                !canOperate && 'opacity-60',
                className,
            )}
            data-camera-uuid={cameraUuid}
            onDoubleClick={(event) => {
                event.stopPropagation();
            }}
        >
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="text-text-muted text-xs font-medium tracking-wide uppercase">
                    PTZ · {cameraName}
                </div>
            </div>
            {!isOnline && (
                <p className="mb-2 text-xs text-[color:var(--warn)]">
                    Camera offline — PTZ disabled until the stream recovers.
                </p>
            )}
            <div className="flex items-end gap-4">
                <div className="grid grid-cols-3 gap-1">
                    <div />
                    <Button
                        type="button"
                        size="icon"
                        variant={activeKey === 'up' ? 'default' : 'secondary'}
                        className="size-10"
                        aria-label="Tilt up"
                        aria-pressed={activeKey === 'up'}
                        {...bindMove('up', 0, PTZ_SPEED)}
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <div />
                    <Button
                        type="button"
                        size="icon"
                        variant={activeKey === 'left' ? 'default' : 'secondary'}
                        className="size-10"
                        aria-label="Pan left"
                        aria-pressed={activeKey === 'left'}
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
                        disabled={!canOperate}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleStop();
                        }}
                    >
                        <Square className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant={
                            activeKey === 'right' ? 'default' : 'secondary'
                        }
                        className="size-10"
                        aria-label="Pan right"
                        aria-pressed={activeKey === 'right'}
                        {...bindMove('right', PTZ_SPEED, 0)}
                    >
                        <ArrowRight className="size-4" />
                    </Button>
                    <div />
                    <Button
                        type="button"
                        size="icon"
                        variant={activeKey === 'down' ? 'default' : 'secondary'}
                        className="size-10"
                        aria-label="Tilt down"
                        aria-pressed={activeKey === 'down'}
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
                        variant={
                            activeKey === 'zoom-in' ? 'default' : 'secondary'
                        }
                        className="size-10"
                        aria-label="Zoom in"
                        aria-pressed={activeKey === 'zoom-in'}
                        {...bindMove('zoom-in', 0, 0, PTZ_SPEED)}
                    >
                        <Plus className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant={
                            activeKey === 'zoom-out' ? 'default' : 'secondary'
                        }
                        className="size-10"
                        aria-label="Zoom out"
                        aria-pressed={activeKey === 'zoom-out'}
                        {...bindMove('zoom-out', 0, 0, -PTZ_SPEED)}
                    >
                        <Minus className="size-4" />
                    </Button>
                </div>
            </div>
            <p className="mt-2 text-[10px] leading-snug text-text-faint">
                Hold to move. Release, stop, or exit fullscreen to halt.
            </p>
        </div>
    );
}
