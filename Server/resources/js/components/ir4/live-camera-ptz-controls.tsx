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
import { ptzMoveVectors } from '@/lib/camera-ptz-vectors';
import type { PtzMoveKey } from '@/lib/camera-ptz-vectors';
import { cn } from '@/lib/utils';

type Props = {
    cameraUuid: string;
    cameraName: string;
    ptzUrl: string;
    enabled?: boolean;
    isOnline?: boolean;
    onInteract?: () => void;
    className?: string;
};

export function LiveCameraPtzControls({
    cameraUuid,
    cameraName,
    ptzUrl,
    enabled = true,
    isOnline = true,
    onInteract,
    className,
}: Props) {
    const canOperate = enabled && isOnline;
    const { activeKey, isBusy, nudge, stop } = useCameraPtz(ptzUrl, canOperate);

    const handleNudge = (key: PtzMoveKey): void => {
        if (!canOperate || isBusy) {
            return;
        }

        const vector = ptzMoveVectors[key];
        onInteract?.();
        void nudge(key, vector.pan, vector.tilt, vector.zoom);
    };

    const handleStop = (): void => {
        if (!canOperate || isBusy) {
            return;
        }

        onInteract?.();
        void stop();
    };

    return (
        <div
            className={cn(
                'pointer-events-auto rounded-[var(--radius)] border border-border/60 bg-black/75 p-3 text-text shadow-lg backdrop-blur-sm select-none',
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
                        disabled={!canOperate || isBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleNudge('up');
                        }}
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
                        disabled={!canOperate || isBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleNudge('left');
                        }}
                    >
                        <ArrowLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-10"
                        aria-label="Stop PTZ"
                        disabled={!canOperate || isBusy}
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
                        disabled={!canOperate || isBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleNudge('right');
                        }}
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
                        disabled={!canOperate || isBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleNudge('down');
                        }}
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
                        disabled={!canOperate || isBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleNudge('zoom-in');
                        }}
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
                        disabled={!canOperate || isBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            handleNudge('zoom-out');
                        }}
                    >
                        <Minus className="size-4" />
                    </Button>
                </div>
            </div>
            <p className="mt-2 text-[10px] leading-snug text-text-faint">
                Click to nudge a few degrees. Stop halts any in-flight motion.
            </p>
        </div>
    );
}
