import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Button } from '@/components/ui/button';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { ViolationTypeLabels } from '@/types/enums';
import type { LiveCamera, PpeViolation } from '@/types/ppe';

type Props = {
    cameras: LiveCamera[];
    recentViolations: PpeViolation[];
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
    recentViolations: initial,
    displayMode = false,
    canViewPpe,
}: Props) {
    const [recent, setRecent] = useState<PpeViolation[]>(initial);

    const { status } = useReverbChannel({
        channel: 'ppe',
        events: ['.PpeViolationDetected'],
        onEvent: (payload: unknown) => {
            const event = payload as ToastPayload;
            toast.warning(
                `${ViolationTypeLabels[event.violation_type as keyof typeof ViolationTypeLabels] ?? event.violation_type} @ ${event.camera_ref}`,
            );
            setRecent((prev) => {
                const next: PpeViolation = {
                    id: event.id,
                    camera_id: 0,
                    camera_ref: event.camera_ref,
                    camera_name: null,
                    violation_type: event.violation_type,
                    detected_at: event.detected_at,
                    worker_count: 1,
                    confidence: null,
                    location_label: null,
                    alert_id: null,
                    review_status: 'unreviewed',
                    reviewed_by: null,
                    reviewed_by_name: null,
                    reviewed_at: null,
                    review_note: null,
                    is_backfill: false,
                    snapshot_url: event.snapshot_url,
                };

                return [next, ...prev.filter((v) => v.id !== event.id)].slice(
                    0,
                    20,
                );
            });
        },
        snapshotUrl: '/api/live/violations',
        onSnapshot: (data) => {
            const json = data as { data: { violations: PpeViolation[] } };
            setRecent(json.data.violations);
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
                            <div className="flex aspect-video items-center justify-center bg-muted/40 text-xs text-muted-foreground">
                                {camera.stream_url ? (
                                    <span className="truncate px-3">
                                        {camera.stream_url}
                                    </span>
                                ) : (
                                    <span>No stream</span>
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

                <section className="space-y-2">
                    <h2 className="text-sm font-medium">Recent unreviewed</h2>
                    <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {recent.map((row) => (
                            <li
                                key={row.id}
                                className="flex gap-3 rounded-lg border border-border p-2 text-sm"
                            >
                                <img
                                    src={row.snapshot_url}
                                    alt=""
                                    className="h-14 w-20 rounded object-cover"
                                />
                                <div className="min-w-0">
                                    <div className="truncate font-medium">
                                        {ViolationTypeLabels[
                                            row.violation_type as keyof typeof ViolationTypeLabels
                                        ] ?? row.violation_type}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {row.camera_ref}
                                    </div>
                                </div>
                            </li>
                        ))}
                        {recent.length === 0 && (
                            <li className="text-sm text-muted-foreground">
                                No recent violations
                            </li>
                        )}
                    </ul>
                </section>
            </div>
        </>
    );
}
