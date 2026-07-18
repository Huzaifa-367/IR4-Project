import { Head, Link } from '@inertiajs/react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Button } from '@/components/ui/button';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { ViolationTypeLabels } from '@/types/enums';
import type { LiveCamera } from '@/types/ppe';

type Props = {
    cameras: LiveCamera[];
    displayMode?: boolean;
    canViewPpe: boolean;
};

type ToastPayload = {
    id: number;
    violation_type: string;
    camera_ref: string;
    snapshot_url: string;
    detected_at: string;
};

export default function LiveWall({
    cameras,
    displayMode = false,
    canViewPpe,
}: Props) {
    const { status } = useReverbChannel({
        channel: 'ppe',
        events: ['.PpeViolationDetected'],
        onEvent: (payload: unknown) => {
            const event = payload as ToastPayload;
            toast.warning(
                `${ViolationTypeLabels[event.violation_type as keyof typeof ViolationTypeLabels] ?? event.violation_type} @ ${event.camera_ref}`,
            );
        },
        pollIntervalMs: 30_000,
    });

    return (
        <>
            <Head title="Live wall" />
            <div className={displayMode ? 'space-y-4' : 'space-y-6 p-6'}>
                {!displayMode && (
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <Heading
                            title="Live camera wall"
                            description={`${cameras.length} cameras`}
                        />
                        <div className="flex items-center gap-2">
                            <LiveStatusPill status={status} />
                            {canViewPpe && (
                                <Button asChild size="sm" variant="secondary">
                                    <Link href="/ppe/violations">PPE log</Link>
                                </Button>
                            )}
                            <Button asChild size="sm" variant="outline">
                                <Link href="/live?display=1">Kiosk</Link>
                            </Button>
                        </div>
                    </div>
                )}
                {displayMode && (
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Live wall
                        </h1>
                        <LiveStatusPill status={status} />
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {cameras.map((camera) => (
                        <div
                            key={camera.id}
                            className="overflow-hidden rounded-lg border border-border bg-card"
                        >
                            <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-2 text-sm">
                                <div className="truncate font-medium">
                                    {camera.name}
                                </div>
                                <div className="flex shrink-0 items-center gap-2 text-xs">
                                    {camera.ai_enabled && (
                                        <span className="text-primary">AI</span>
                                    )}
                                    <span
                                        className={
                                            camera.is_online
                                                ? 'text-emerald-600'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {camera.is_online
                                            ? 'Online'
                                            : camera.status}
                                    </span>
                                </div>
                            </div>
                            <div className="aspect-video bg-black">
                                {camera.playback_url ? (
                                    <iframe
                                        src={camera.playback_url}
                                        title={`${camera.name} live feed`}
                                        className="size-full border-0"
                                        allow="autoplay; fullscreen; picture-in-picture"
                                        allowFullScreen
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                                        No browser stream configured
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    {cameras.length === 0 && (
                        <div className="col-span-full rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground">
                            No cameras registered
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
