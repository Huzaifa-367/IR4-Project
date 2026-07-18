import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { StatCard } from '@/components/ir4/stat-card';
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

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total on site"
                        value={headcount.total_on_site}
                    />
                    {headcount.by_zone.map((zone) => (
                        <StatCard
                            key={zone.zone_id}
                            label={zone.zone_name}
                            value={zone.count}
                        />
                    ))}
                </div>

                {canSeePositions ? (
                    <ZoneMap zones={zones} positions={positions} />
                ) : (
                    <p className="text-sm text-text-faint">
                        Headcount-only view — position map requires additional
                        permissions.
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href="/tracking/tags">Tags</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/tracking/workers">Workers</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/tracking/entry-exit">Entry / exit</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
