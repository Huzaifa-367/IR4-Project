import { Head, Link } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { LiveCameraFeed } from '@/components/ir4/live-camera-feed';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import {
    combineReverbStatus,
    useReverbChannel,
} from '@/hooks/use-reverb-channel';
import live from '@/routes/live';
import ppe from '@/routes/ppe';
import { ViolationTypeLabels } from '@/types/enums';
import type { LiveCamera } from '@/types/ppe';

type Props = {
    cameras: LiveCamera[];
    displayMode?: boolean;
    canViewPpe: boolean;
    canControlPtz: boolean;
};

type ToastPayload = {
    id: number;
    violation_type: string;
    camera_ref: string;
    snapshot_url: string | null;
    detected_at: string;
};

type DeviceStatusPayload = {
    device_id: number;
    status: string;
    device_type?: string;
    device_name: string;
};

function unwrapCameras(payload: unknown): LiveCamera[] | null {
    if (!payload || typeof payload !== 'object' || !('data' in payload)) {
        return null;
    }

    const cameras = (payload as { data?: { cameras?: unknown } }).data?.cameras;

    return Array.isArray(cameras) ? (cameras as LiveCamera[]) : null;
}

function patchCameraChip(
    cameras: LiveCamera[],
    payload: DeviceStatusPayload,
): LiveCamera[] {
    const isCameraEvent =
        payload.device_type === 'camera' ||
        cameras.some((camera) => camera.name === payload.device_name);

    if (!isCameraEvent) {
        return cameras;
    }

    const offline = payload.status === 'offline' || payload.status === 'fault';

    return cameras.map((camera) => {
        const matches =
            (payload.device_type === 'camera' &&
                camera.id === payload.device_id) ||
            camera.name === payload.device_name;

        if (!matches) {
            return camera;
        }

        return {
            ...camera,
            status: payload.status,
            is_online: !offline,
        };
    });
}

export default function LiveWall({
    cameras: initialCameras,
    displayMode = false,
    canViewPpe,
    canControlPtz,
}: Props) {
    const [cameras, setCameras] = usePropSyncedState(initialCameras);
    /** Client blank/stall — hide player until poll/status retry. */
    const [blankFeedIds, setBlankFeedIds] = useState<ReadonlySet<number>>(
        () => new Set(),
    );

    const markFeedBlank = useCallback((cameraId: number): void => {
        setBlankFeedIds((current) => {
            if (current.has(cameraId)) {
                return current;
            }

            const next = new Set(current);
            next.add(cameraId);

            return next;
        });
    }, []);

    const clearBlankFeeds = useCallback((ids?: number[]): void => {
        setBlankFeedIds((current) => {
            if (current.size === 0) {
                return current;
            }

            if (ids === undefined) {
                return new Set();
            }

            const next = new Set(current);

            for (const id of ids) {
                next.delete(id);
            }

            return next.size === current.size ? current : next;
        });
    }, []);

    const ppeLive = useReverbChannel({
        channel: 'ppe',
        events: ['.PpeViolationDetected'],
        onEvent: (payload: unknown) => {
            const event = payload as ToastPayload;
            toast.warning(
                `${ViolationTypeLabels[event.violation_type as keyof typeof ViolationTypeLabels] ?? event.violation_type} @ ${event.camera_ref}`,
            );
        },
        snapshotUrl: live.violations.url(),
        onSnapshot: (payload: unknown) => {
            const next = unwrapCameras(payload);

            if (next) {
                setCameras(next);
                clearBlankFeeds(
                    next.filter((camera) => camera.is_online).map((c) => c.id),
                );
            }
        },
        pollIntervalMs: 30_000,
    });

    const systemLive = useReverbChannel({
        channel: 'system',
        events: ['.DeviceStatusChanged'],
        onEvent: (payload: unknown) => {
            const event = payload as DeviceStatusPayload;

            if (typeof event.device_name !== 'string') {
                return;
            }

            const offline =
                event.status === 'offline' || event.status === 'fault';

            setCameras((current) => patchCameraChip(current, event));

            if (offline) {
                markFeedBlank(event.device_id);
            } else if (event.device_type === 'camera') {
                clearBlankFeeds([event.device_id]);
            }
        },
        pollIntervalMs: 30_000,
    });

    const status = combineReverbStatus(ppeLive.status, systemLive.status);

    const liveCameras = cameras.filter(
        (camera) => camera.is_online && !blankFeedIds.has(camera.id),
    );
    const downCameras = cameras.filter(
        (camera) => !camera.is_online || blankFeedIds.has(camera.id),
    );

    return (
        <>
            <Head title="Live wall" />
            <div className={displayMode ? 'space-y-4' : 'space-y-6 p-6'}>
                {!displayMode && (
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <Heading
                            title="Live camera wall"
                            description={`${liveCameras.length} live · ${downCameras.length} down`}
                        />
                        <div className="flex items-center gap-2">
                            {canViewPpe && (
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={ppe.violations.index()}>
                                        PPE log
                                    </Link>
                                </Button>
                            )}
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={live.index.url({
                                        query: { display: '1' },
                                    })}
                                >
                                    Kiosk
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}
                {displayMode && (
                    <div className="flex items-center justify-between">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Live wall
                        </h1>
                        <LiveStatusPill status={status} />
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {liveCameras.map((camera) => (
                        <div
                            key={camera.id}
                            className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface"
                        >
                            <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-2 text-sm">
                                <div className="truncate font-medium text-text">
                                    {camera.name}
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    {camera.ai_enabled && (
                                        <StatusPill
                                            label="AI"
                                            tone="accent"
                                            showDot={false}
                                        />
                                    )}
                                    <StatusPill label="Online" tone="ok" />
                                </div>
                            </div>
                            <div className="aspect-video bg-black">
                                {camera.playback_url ? (
                                    <LiveCameraFeed
                                        playbackUrl={camera.playback_url}
                                        title={`${camera.name} live feed`}
                                        cameraUuid={camera.uuid}
                                        cameraName={camera.name}
                                        ptzUrl={
                                            camera.can_control_ptz
                                                ? live.cameras.ptz.url(
                                                      camera.uuid,
                                                  )
                                                : null
                                        }
                                        canControlPtz={
                                            canControlPtz &&
                                            camera.can_control_ptz
                                        }
                                        onDown={() => {
                                            markFeedBlank(camera.id);
                                        }}
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-xs text-text-faint">
                                        No browser stream configured
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    {cameras.length === 0 && (
                        <div className="col-span-full rounded-[var(--radius)] border border-dashed border-border p-8 text-center text-text-faint">
                            No cameras registered
                        </div>
                    )}
                </div>

                {downCameras.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-xs font-medium tracking-wide text-text-faint uppercase">
                            Down ({downCameras.length})
                        </p>
                        <ul className="flex flex-wrap gap-2">
                            {downCameras.map((camera) => (
                                <li
                                    key={camera.id}
                                    className="flex items-center gap-2 rounded-[var(--radius)] border border-[color:var(--crit)]/40 bg-[#0b0d10] px-3 py-2 text-sm"
                                >
                                    <StatusPill label="Down" tone="crit" />
                                    <span className="font-medium text-text">
                                        {camera.name}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}
