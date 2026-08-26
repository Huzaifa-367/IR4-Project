import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { FILTER_SEARCH_DEBOUNCE_MS, visitFilters } from '@/lib/visit-filters';
import tracking from '@/routes/tracking';
import type { PaginatedMeta } from '@/types/hardware';
import type { HeadcountReading } from '@/types/tracking';

type Props = {
    readings: { data: HeadcountReading[]; meta: PaginatedMeta };
    filters: {
        zone_id: string;
        camera_id: string;
        from: string;
        to: string;
        backfill: string;
        search: string;
    };
    zones: Array<{ id: number; name: string }>;
    cameras: Array<{
        id: number;
        name: string | null;
        reference: string | null;
    }>;
};

const ALL = 'all';

export default function HeadcountReadingsIndex({
    readings,
    filters,
    zones,
    cameras,
}: Props) {
    const [zoneId, setZoneId] = useState(filters.zone_id || ALL);
    const [cameraId, setCameraId] = useState(filters.camera_id || ALL);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [backfill, setBackfill] = useState(filters.backfill || ALL);
    const [search, setSearch] = useState(filters.search);

    function applyFilters(
        patch: Partial<{
            zone_id: string;
            camera_id: string;
            from: string;
            to: string;
            backfill: string;
            search: string;
        }> = {},
    ): void {
        const nextZone = patch.zone_id ?? zoneId;
        const nextCamera = patch.camera_id ?? cameraId;
        const nextFrom = patch.from ?? from;
        const nextTo = patch.to ?? to;
        const nextBackfill = patch.backfill ?? backfill;
        const nextSearch = patch.search ?? search;

        visitFilters(tracking.headcountReadings.index.url(), {
            zone_id: nextZone === ALL ? undefined : nextZone,
            camera_id: nextCamera === ALL ? undefined : nextCamera,
            from: nextFrom || undefined,
            to: nextTo || undefined,
            backfill: nextBackfill === ALL ? undefined : nextBackfill,
            search: nextSearch || undefined,
        });
    }

    const [applySearch] = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, FILTER_SEARCH_DEBOUNCE_MS);

    const queryParams = {
        zone_id: zoneId === ALL ? undefined : zoneId,
        camera_id: cameraId === ALL ? undefined : cameraId,
        from: from || undefined,
        to: to || undefined,
        backfill: backfill === ALL ? undefined : backfill,
        search: search || undefined,
    };

    const columns: SettingsColumn<HeadcountReading>[] = [
        {
            key: 'number',
            header: 'Number',
            className: 'w-28',
            cell: (row) => (
                <span className="font-mono text-xs">Sample #{row.id}</span>
            ),
        },
        {
            key: 'when',
            header: 'When',
            cell: (row) => (
                <span className="font-mono text-xs whitespace-nowrap">
                    {new Date(row.recorded_at).toLocaleString()}
                </span>
            ),
        },
        {
            key: 'zone',
            header: 'Zone',
            cell: (row) => row.zone_name ?? 'Unbound',
        },
        {
            key: 'camera',
            header: 'Camera',
            cell: (row) => (
                <span className="font-mono text-xs">
                    {row.camera_ref ?? row.camera_name ?? '—'}
                </span>
            ),
        },
        {
            key: 'count',
            header: 'Count',
            className: 'text-right font-mono tabular-nums',
            cell: (row) => row.count,
        },
        {
            key: 'kind',
            header: 'Kind',
            cell: (row) =>
                row.is_backfill ? (
                    <StatusPill label="Backfill" tone="neutral" />
                ) : (
                    <StatusPill label="Live" tone="ok" />
                ),
        },
    ];

    return (
        <>
            <Head title="Headcount records" />
            <SettingsPageShell
                eyebrow="Tracking"
                title="Headcount records"
                description="Every on-site count sample — filter by time, zone, or camera"
                actions={
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={tracking.readings.index()}>
                                Tag readings
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={tracking.index()}>Live tracking</Link>
                        </Button>
                    </div>
                }
                filters={
                    <div className="flex w-full min-w-0 flex-col gap-2">
                        <div className="grid w-full grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
                            <Input
                                type="search"
                                placeholder="Camera or zone"
                                value={search}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setSearch(value);
                                    applySearch(value);
                                }}
                                aria-label="Search camera or zone"
                                className="min-w-0"
                            />
                            <SearchableSelect
                                value={zoneId}
                                onValueChange={(value) => {
                                    setZoneId(value);
                                    applyFilters({ zone_id: value });
                                }}
                                placeholder="Zone"
                                className="min-w-0"
                                options={[
                                    { value: ALL, label: 'All zones' },
                                    { value: 'unbound', label: 'Unbound' },
                                    ...zones.map((zone) => ({
                                        value: String(zone.id),
                                        label: zone.name,
                                    })),
                                ]}
                            />
                            <SearchableSelect
                                value={cameraId}
                                onValueChange={(value) => {
                                    setCameraId(value);
                                    applyFilters({ camera_id: value });
                                }}
                                placeholder="Camera"
                                className="min-w-0"
                                options={[
                                    { value: ALL, label: 'All cameras' },
                                    ...cameras.map((camera) => ({
                                        value: String(camera.id),
                                        label:
                                            camera.reference ??
                                            camera.name ??
                                            `#${camera.id}`,
                                    })),
                                ]}
                            />
                            <SearchableSelect
                                value={backfill}
                                onValueChange={(value) => {
                                    setBackfill(value);
                                    applyFilters({ backfill: value });
                                }}
                                placeholder="Kind"
                                className="min-w-0"
                                options={[
                                    { value: ALL, label: 'Live + backfill' },
                                    { value: 'live', label: 'Live only' },
                                    {
                                        value: 'backfill',
                                        label: 'Backfill only',
                                    },
                                ]}
                            />
                        </div>
                        <div className="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 xl:max-w-2xl">
                            <label className="grid min-w-0 gap-1">
                                <span className="text-[11px] font-medium tracking-wide text-text-faint uppercase">
                                    From
                                </span>
                                <Input
                                    type="datetime-local"
                                    value={from}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setFrom(value);
                                        applyFilters({ from: value });
                                    }}
                                    className="min-w-0"
                                />
                            </label>
                            <label className="grid min-w-0 gap-1">
                                <span className="text-[11px] font-medium tracking-wide text-text-faint uppercase">
                                    To
                                </span>
                                <Input
                                    type="datetime-local"
                                    value={to}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setTo(value);
                                        applyFilters({ to: value });
                                    }}
                                    className="min-w-0"
                                />
                            </label>
                        </div>
                    </div>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={readings.data}
                    rowKey={(row) => row.id}
                    meta={readings.meta}
                    pageUrl={tracking.headcountReadings.index.url()}
                    queryParams={queryParams}
                    emptyTitle="No headcount records"
                    emptyDescription="No headcount samples match these filters."
                />
            </SettingsPageShell>
        </>
    );
}
