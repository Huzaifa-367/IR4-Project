import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { MetricRow } from '@/components/ir4/metric-row';
import { Panel } from '@/components/ir4/panel';
import {
    ZoneCoverageTable,
    ZoneHeadcountReadingsTable,
    ZoneOccupancyTable,
    ZonePresenceTable,
    ZoneReadingsTable,
} from '@/components/ir4/zone-tables';
import { Button } from '@/components/ui/button';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import settings from '@/routes/settings';
import tracking from '@/routes/tracking';
import type {
    HeadcountReading,
    HeadcountSnapshot,
    TrackingCoverage,
    TrackingPosition,
    TrackingReading,
    TrackingZone,
} from '@/types/tracking';

type Props = {
    headcount: HeadcountSnapshot;
    zones: TrackingZone[];
    positions: TrackingPosition[];
    coverage: TrackingCoverage[];
    readings: TrackingReading[];
    headcountReadings: HeadcountReading[];
    canSeePositions: boolean;
    canTriggerEvacuation: boolean;
};

type PositionDelta = {
    tag_id: number;
    worker_id?: number | null;
    zone_id?: number | null;
    last_seen_at?: string;
    is_on_site?: boolean;
};

function applyPositionDeltas(
    current: TrackingPosition[],
    deltas: PositionDelta[],
    zones: TrackingZone[],
): TrackingPosition[] {
    const byTag = new Map(current.map((row) => [row.tag_id, row]));
    const zoneNames = new Map(zones.map((zone) => [zone.id, zone.name]));

    for (const delta of deltas) {
        if (delta.is_on_site === false) {
            byTag.delete(delta.tag_id);
            continue;
        }

        const existing = byTag.get(delta.tag_id);
        const workerId = delta.worker_id ?? existing?.worker_id ?? 0;
        const zoneId = delta.zone_id ?? existing?.zone_id ?? null;

        byTag.set(delta.tag_id, {
            tag_id: delta.tag_id,
            tag_uid: existing?.tag_uid ?? null,
            worker_id: workerId,
            worker_label:
                existing?.worker_label ??
                (workerId > 0 ? `Worker #${String(workerId)}` : 'Worker'),
            zone_id: zoneId,
            zone_name:
                zoneId !== null
                    ? (zoneNames.get(zoneId) ?? existing?.zone_name ?? null)
                    : null,
            last_seen_at:
                delta.last_seen_at ??
                existing?.last_seen_at ??
                new Date().toISOString(),
            is_on_site: true,
        });
    }

    return [...byTag.values()];
}

export default function TrackingIndex({
    headcount: initialHeadcount,
    zones: initialZones,
    positions: initialPositions,
    coverage: initialCoverage,
    readings: initialReadings,
    headcountReadings: initialHeadcountReadings,
    canSeePositions,
    canTriggerEvacuation,
}: Props) {
    const [headcount, setHeadcount] = usePropSyncedState(initialHeadcount);
    const [zones, setZones] = usePropSyncedState(initialZones);
    const [positions, setPositions] = usePropSyncedState(initialPositions);
    const [coverage, setCoverage] = usePropSyncedState(initialCoverage);
    const [readings, setReadings] = usePropSyncedState(initialReadings);
    const [headcountReadings, setHeadcountReadings] = usePropSyncedState(
        initialHeadcountReadings,
    );
    const [zoneFilter, setZoneFilter] = useState<number | 'all'>('all');
    const [headcountZoneFilter, setHeadcountZoneFilter] = useState<
        number | 'all'
    >('all');

    const loadReadings = useCallback(
        async (zoneId: number | 'all'): Promise<void> => {
            const res = await fetch(
                tracking.api.readings.url({
                    query:
                        zoneId === 'all'
                            ? { limit: 25 }
                            : { zone_id: zoneId, limit: 25 },
                }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (res.ok) {
                const json = (await res.json()) as { data: TrackingReading[] };
                setReadings(json.data);
            }
        },
        [setReadings],
    );

    const loadHeadcountReadings = useCallback(
        async (zoneId: number | 'all'): Promise<void> => {
            const res = await fetch(
                tracking.api.headcountReadings.url({
                    query:
                        zoneId === 'all'
                            ? { limit: 25 }
                            : { zone_id: zoneId, limit: 25 },
                }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (res.ok) {
                const json = (await res.json()) as { data: HeadcountReading[] };
                setHeadcountReadings(json.data);
            }
        },
        [setHeadcountReadings],
    );

    const loadSnapshots = useCallback(async (): Promise<void> => {
        const headRes = await fetch(tracking.api.headcount.url(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (headRes.ok) {
            const json = (await headRes.json()) as { data: HeadcountSnapshot };
            setHeadcount(json.data);
        }

        await loadHeadcountReadings(headcountZoneFilter);

        if (!canSeePositions) {
            return;
        }

        const [posRes, covRes] = await Promise.all([
            fetch(tracking.api.positions.url(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }),
            fetch(tracking.coverage.url(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }),
        ]);

        if (posRes.ok) {
            const json = (await posRes.json()) as {
                data: { zones: TrackingZone[]; positions: TrackingPosition[] };
            };
            setZones(json.data.zones);
            setPositions(json.data.positions);
        }

        if (covRes.ok) {
            const json = (await covRes.json()) as { data: TrackingCoverage[] };
            setCoverage(json.data);
        }

        await loadReadings(zoneFilter);
    }, [
        canSeePositions,
        headcountZoneFilter,
        loadHeadcountReadings,
        loadReadings,
        setCoverage,
        setHeadcount,
        setPositions,
        setZones,
        zoneFilter,
    ]);

    useReverbChannel({
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
                void loadHeadcountReadings(headcountZoneFilter);
            }

            if ('positions' in p && Array.isArray(p.positions)) {
                setPositions((current) =>
                    applyPositionDeltas(
                        current,
                        p.positions as PositionDelta[],
                        zones,
                    ),
                );
            }

            if (typeof p.uuid === 'string' && 'report_id' in p) {
                router.visit(tracking.evacuation.show.url(p.uuid));
            }
        },
        snapshotUrl: tracking.api.headcount.url(),
        onSnapshot: (data) => {
            const json = data as { data: HeadcountSnapshot };
            setHeadcount(json.data);
            void loadSnapshots();
        },
        pollIntervalMs: 30_000,
    });

    const occupiedZones = headcount.by_zone.filter(
        (zone) => zone.count > 0,
    ).length;
    const overLimit = useMemo(() => {
        const counts = new Map(
            headcount.by_zone.map((row) => [row.zone_id, row.count]),
        );

        return zones.filter((zone) => {
            const limit = zone.occupancy_limit;

            if (limit == null) {
                return false;
            }

            return (counts.get(zone.id) ?? 0) > limit;
        }).length;
    }, [headcount.by_zone, zones]);
    const boundReaders = coverage.filter((row) => row.zone !== null).length;
    const unboundReaders = coverage.filter((row) => row.zone === null).length;
    const lastSampleAt =
        headcount.as_of ??
        headcountReadings[0]?.recorded_at ??
        readings[0]?.recorded_at ??
        null;
    const lastReading = lastSampleAt
        ? new Date(lastSampleAt).toLocaleString()
        : '—';

    const occupancyZones =
        zones.length > 0
            ? zones
            : headcount.by_zone.map((row) => ({
                  id: row.zone_id,
                  uuid: String(row.zone_id),
                  name: row.zone_name,
                  zone_type: 'work',
                  color: null,
              }));

    return (
        <>
            <Head title="Tracking" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Live tracking"
                        description="Who is on site now, zone occupancy, and latest activity"
                    />
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => void loadSnapshots()}
                        >
                            Refresh
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={tracking.readings.index()}>
                                Tag readings
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={tracking.headcountReadings.index()}>
                                Headcount records
                            </Link>
                        </Button>
                        {canTriggerEvacuation && (
                            <Button asChild variant="destructive" size="sm">
                                <Link href={tracking.evacuation.index()}>
                                    Evacuation
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Panel title="Now" subtitle="Live on-site total">
                    <MetricRow
                        className="sm:grid-cols-2 lg:grid-cols-5"
                        items={[
                            {
                                label: 'On site',
                                value: headcount.total_on_site,
                            },
                            {
                                label: 'Occupied zones',
                                value: occupiedZones,
                            },
                            {
                                label: 'Over limit',
                                value: overLimit,
                                deltaTone: overLimit > 0 ? 'crit' : 'ok',
                            },
                            {
                                label: 'Readers bound',
                                value: canSeePositions
                                    ? `${boundReaders}/${coverage.length || 0}`
                                    : '—',
                                delta:
                                    unboundReaders > 0
                                        ? `${unboundReaders} unbound`
                                        : undefined,
                                deltaTone:
                                    unboundReaders > 0 ? 'crit' : 'neutral',
                            },
                            {
                                label: 'Last sample',
                                value: lastReading,
                            },
                        ]}
                    />
                </Panel>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel
                        title="Zone occupancy"
                        subtitle="Live counts by zone"
                        className="xl:col-span-5"
                    >
                        <ZoneOccupancyTable
                            zones={occupancyZones}
                            occupancy={headcount.by_zone}
                            onSelect={
                                canSeePositions
                                    ? (zone) =>
                                          router.visit(
                                              settings.zones.show.url(
                                                  zone.uuid,
                                              ),
                                          )
                                    : undefined
                            }
                        />
                    </Panel>
                    <Panel
                        title="Latest headcount"
                        subtitle="Most recent 25 samples — open Headcount records for history"
                        className="xl:col-span-7"
                        action={
                            <div className="flex items-center gap-2">
                                <select
                                    className="rounded-[var(--radius-sm)] border border-border bg-surface-2 px-2 py-1 text-xs"
                                    value={
                                        headcountZoneFilter === 'all'
                                            ? 'all'
                                            : String(headcountZoneFilter)
                                    }
                                    onChange={(event) => {
                                        const next =
                                            event.target.value === 'all'
                                                ? 'all'
                                                : Number(event.target.value);
                                        setHeadcountZoneFilter(next);
                                        void loadHeadcountReadings(next);
                                    }}
                                >
                                    <option value="all">All zones</option>
                                    {occupancyZones.map((zone) => (
                                        <option key={zone.id} value={zone.id}>
                                            {zone.name}
                                        </option>
                                    ))}
                                </select>
                                <Link
                                    href={
                                        headcountZoneFilter === 'all'
                                            ? tracking.headcountReadings.index()
                                            : tracking.headcountReadings.index.url(
                                                  {
                                                      query: {
                                                          zone_id:
                                                              headcountZoneFilter,
                                                      },
                                                  },
                                              )
                                    }
                                    className="text-xs text-[color:var(--accent)] hover:underline"
                                >
                                    All records ›
                                </Link>
                            </div>
                        }
                    >
                        <ZoneHeadcountReadingsTable
                            readings={headcountReadings}
                        />
                    </Panel>
                </div>

                {canSeePositions ? (
                    <div className="grid gap-4 xl:grid-cols-12">
                        <Panel
                            title="On site now"
                            subtitle="Latest resolved position per tag"
                            className="xl:col-span-7"
                        >
                            <ZonePresenceTable positions={positions} />
                        </Panel>
                        <Panel
                            title="Reader coverage"
                            subtitle="Which RFID reader is assigned to which zone"
                            className="xl:col-span-5"
                            action={
                                <Link
                                    href={settings.repositioning()}
                                    className="text-xs text-[color:var(--accent)] hover:underline"
                                >
                                    Rebind ›
                                </Link>
                            }
                        >
                            <ZoneCoverageTable coverage={coverage} />
                        </Panel>
                        <Panel
                            title="Latest tag readings"
                            subtitle="Most recent 25 — open Tag readings for history and filters"
                            className="xl:col-span-12"
                            action={
                                <div className="flex items-center gap-2">
                                    <select
                                        className="rounded-[var(--radius-sm)] border border-border bg-surface-2 px-2 py-1 text-xs"
                                        value={
                                            zoneFilter === 'all'
                                                ? 'all'
                                                : String(zoneFilter)
                                        }
                                        onChange={(event) => {
                                            const next =
                                                event.target.value === 'all'
                                                    ? 'all'
                                                    : Number(
                                                          event.target.value,
                                                      );
                                            setZoneFilter(next);
                                            void loadReadings(next);
                                        }}
                                    >
                                        <option value="all">All zones</option>
                                        {zones.map((zone) => (
                                            <option
                                                key={zone.id}
                                                value={zone.id}
                                            >
                                                {zone.name}
                                            </option>
                                        ))}
                                    </select>
                                    <Link
                                        href={
                                            zoneFilter === 'all'
                                                ? tracking.readings.index()
                                                : tracking.readings.index.url({
                                                      query: {
                                                          zone_id: zoneFilter,
                                                      },
                                                  })
                                        }
                                        className="text-xs text-[color:var(--accent)] hover:underline"
                                    >
                                        All records ›
                                    </Link>
                                </div>
                            }
                        >
                            <ZoneReadingsTable readings={readings} />
                        </Panel>
                    </div>
                ) : (
                    <p className="text-sm text-text-faint">
                        Presence, reader coverage, and tag readings require
                        additional permissions.
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href={tracking.tags.index()}>Tags</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={tracking.workers.index()}>Workers</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={tracking.entryExit.index()}>
                            Entry / exit
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={settings.zones.index()}>Zones</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
