import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { ZoneMap } from '@/components/ir4/zone-map';
import { Button } from '@/components/ui/button';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import type {
    HeadcountSnapshot,
    TrackingPosition,
    TrackingZone,
} from '@/types/tracking';

type Props = {
    headcount: HeadcountSnapshot;
    canSeePositions: boolean;
    canTriggerEvacuation: boolean;
};

export default function TrackingIndex({
    headcount: initialHeadcount,
    canSeePositions,
    canTriggerEvacuation,
}: Props) {
    const [headcount, setHeadcount] = useState(initialHeadcount);
    const [positions, setPositions] = useState<TrackingPosition[]>([]);
    const [zones, setZones] = useState<TrackingZone[]>([]);

    const loadSnapshots = useCallback(async (): Promise<void> => {
        const headRes = await fetch('/tracking/api/headcount', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (headRes.ok) {
            const json = (await headRes.json()) as { data: HeadcountSnapshot };
            setHeadcount(json.data);
        }

        if (!canSeePositions) {
            return;
        }

        const posRes = await fetch('/tracking/api/positions', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (posRes.ok) {
            const json = (await posRes.json()) as {
                data: { positions: TrackingPosition[]; zones: TrackingZone[] };
            };
            setPositions(json.data.positions);
            setZones(json.data.zones);
        }
    }, [canSeePositions]);

    const { status } = useReverbChannel({
        channel: 'tracking',
        events: [
            '.HeadcountUpdated',
            '.PositionsUpdated',
            '.EvacuationTriggered',
        ],
        onEvent: (payload: unknown) => {
            const p = payload as Record<string, unknown>;

            if ('total_on_site' in p) {
                setHeadcount(p as unknown as HeadcountSnapshot);
            }

            if ('positions' in p) {
                void loadSnapshots();
            }

            if ('report_id' in p) {
                window.location.href = `/tracking/evacuation/${String(p.report_id)}`;
            }
        },
        snapshotUrl: '/tracking/api/headcount',
        onSnapshot: (data) => {
            const json = data as { data: HeadcountSnapshot };
            setHeadcount(json.data);
            void loadSnapshots();
        },
        pollIntervalMs: 30_000,
    });

    useEffect(() => {
        void loadSnapshots();
    }, [loadSnapshots]);

    return (
        <>
            <Head title="Tracking" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Live tracking"
                        description={`${headcount.total_on_site} on site`}
                    />
                    <div className="flex items-center gap-2">
                        <LiveStatusPill status={status} />
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => void loadSnapshots()}
                        >
                            Refresh
                        </Button>
                        {canTriggerEvacuation && (
                            <Button asChild variant="destructive" size="sm">
                                <Link href="/tracking/evacuation">
                                    Evacuation
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-lg border border-border p-4">
                        <div className="text-xs text-muted-foreground">
                            Total on site
                        </div>
                        <div className="text-3xl font-semibold">
                            {headcount.total_on_site}
                        </div>
                    </div>
                    {headcount.by_zone.map((zone) => (
                        <div
                            key={zone.zone_id}
                            className="rounded-lg border border-border p-4"
                        >
                            <div className="text-xs text-muted-foreground">
                                {zone.zone_name}
                            </div>
                            <div className="text-2xl font-semibold">
                                {zone.count}
                            </div>
                        </div>
                    ))}
                </div>

                {canSeePositions ? (
                    <ZoneMap zones={zones} positions={positions} />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Headcount-only view — position map requires additional
                        permissions.
                    </p>
                )}

                <div className="flex flex-wrap gap-3 text-sm">
                    <Link href="/tracking/tags" className="underline">
                        Tags
                    </Link>
                    <Link href="/tracking/workers" className="underline">
                        Workers
                    </Link>
                    <Link href="/tracking/entry-exit" className="underline">
                        Entry / exit
                    </Link>
                </div>
            </div>
        </>
    );
}
