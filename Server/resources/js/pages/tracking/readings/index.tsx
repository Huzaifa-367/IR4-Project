import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { FILTER_SEARCH_DEBOUNCE_MS, visitFilters } from '@/lib/visit-filters';
import tracking from '@/routes/tracking';
import type { PaginatedMeta } from '@/types/hardware';
import type { TrackingReading } from '@/types/tracking';

type Props = {
    readings: { data: TrackingReading[]; meta: PaginatedMeta };
    filters: {
        zone_id: string;
        reader_id: string;
        from: string;
        to: string;
        backfill: string;
        proximity: string;
        search: string;
    };
    zones: Array<{ id: number; name: string }>;
    readers: Array<{
        id: number;
        name: string | null;
        reference: string | null;
    }>;
};

const ALL = 'all';

export default function ReadingsIndex({
    readings,
    filters,
    zones,
    readers,
}: Props) {
    const [zoneId, setZoneId] = useState(filters.zone_id || ALL);
    const [readerId, setReaderId] = useState(filters.reader_id || ALL);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [backfill, setBackfill] = useState(filters.backfill || ALL);
    const [proximity, setProximity] = useState(filters.proximity || ALL);
    const [search, setSearch] = useState(filters.search);

    function applyFilters(
        patch: Partial<{
            zone_id: string;
            reader_id: string;
            from: string;
            to: string;
            backfill: string;
            proximity: string;
            search: string;
        }> = {},
    ): void {
        const nextZone = patch.zone_id ?? zoneId;
        const nextReader = patch.reader_id ?? readerId;
        const nextFrom = patch.from ?? from;
        const nextTo = patch.to ?? to;
        const nextBackfill = patch.backfill ?? backfill;
        const nextProximity = patch.proximity ?? proximity;
        const nextSearch = patch.search ?? search;

        visitFilters(tracking.readings.index.url(), {
            zone_id: nextZone === ALL ? undefined : nextZone,
            reader_id: nextReader === ALL ? undefined : nextReader,
            from: nextFrom || undefined,
            to: nextTo || undefined,
            backfill: nextBackfill === ALL ? undefined : nextBackfill,
            proximity: nextProximity === ALL ? undefined : nextProximity,
            search: nextSearch || undefined,
        });
    }

    const [applySearch] = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, FILTER_SEARCH_DEBOUNCE_MS);

    const queryParams = {
        zone_id: zoneId === ALL ? undefined : zoneId,
        reader_id: readerId === ALL ? undefined : readerId,
        from: from || undefined,
        to: to || undefined,
        backfill: backfill === ALL ? undefined : backfill,
        proximity: proximity === ALL ? undefined : proximity,
        search: search || undefined,
    };

    const columns: SettingsColumn<TrackingReading>[] = [
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
            key: 'reader',
            header: 'Reader',
            cell: (row) => (
                <span className="font-mono text-xs">
                    {row.reader_ref ?? row.reader_name ?? '—'}
                </span>
            ),
        },
        {
            key: 'tag',
            header: 'Tag',
            cell: (row) => (
                <span className="font-mono text-xs">{row.tag_uid ?? '—'}</span>
            ),
        },
        {
            key: 'person',
            header: 'Person',
            cell: (row) => row.worker_label ?? '—',
        },
        {
            key: 'rssi',
            header: 'RSSI',
            className: 'text-right font-mono tabular-nums',
            cell: (row) => row.rssi ?? '—',
        },
        {
            key: 'ant',
            header: 'Ant',
            className: 'text-right font-mono tabular-nums',
            cell: (row) => row.antenna ?? '—',
        },
        {
            key: 'prox',
            header: 'Proximity',
            cell: (row) =>
                row.proximity ? (
                    <StatusPill
                        label={row.proximity}
                        tone={
                            row.proximity === 'near'
                                ? 'ok'
                                : row.proximity === 'mid'
                                  ? 'warn'
                                  : 'neutral'
                        }
                    />
                ) : (
                    '—'
                ),
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
            <Head title="Tag readings" />
            <SettingsPageShell
                eyebrow="Tracking"
                title="Tag readings"
                description="Every RFID read as a record — filter by time, zone, reader, or tag"
                actions={
                    <Link
                        href={tracking.index()}
                        className="text-xs text-[color:var(--accent)] hover:underline"
                    >
                        Live tracking ›
                    </Link>
                }
                filters={
                    <>
                        <Input
                            type="search"
                            placeholder="Tag or reader"
                            value={search}
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                applySearch(value);
                            }}
                            className="w-44"
                        />
                        <SearchableSelect
                            value={zoneId}
                            onValueChange={(value) => {
                                setZoneId(value);
                                applyFilters({ zone_id: value });
                            }}
                            placeholder="Zone"
                            triggerClassName="w-44"
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
                            value={readerId}
                            onValueChange={(value) => {
                                setReaderId(value);
                                applyFilters({ reader_id: value });
                            }}
                            placeholder="Reader"
                            triggerClassName="w-44"
                            options={[
                                { value: ALL, label: 'All readers' },
                                ...readers.map((reader) => ({
                                    value: String(reader.id),
                                    label:
                                        reader.reference ??
                                        reader.name ??
                                        `#${reader.id}`,
                                })),
                            ]}
                        />
                        <Input
                            type="datetime-local"
                            value={from}
                            onChange={(event) => {
                                const value = event.target.value;
                                setFrom(value);
                                applyFilters({ from: value });
                            }}
                            aria-label="From"
                            className="w-48"
                        />
                        <Input
                            type="datetime-local"
                            value={to}
                            onChange={(event) => {
                                const value = event.target.value;
                                setTo(value);
                                applyFilters({ to: value });
                            }}
                            aria-label="To"
                            className="w-48"
                        />
                        <SearchableSelect
                            value={backfill}
                            onValueChange={(value) => {
                                setBackfill(value);
                                applyFilters({ backfill: value });
                            }}
                            placeholder="Kind"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'Live + backfill' },
                                { value: 'live', label: 'Live only' },
                                { value: 'backfill', label: 'Backfill only' },
                            ]}
                        />
                        <SearchableSelect
                            value={proximity}
                            onValueChange={(value) => {
                                setProximity(value);
                                applyFilters({ proximity: value });
                            }}
                            placeholder="Proximity"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'Any proximity' },
                                { value: 'near', label: 'Near' },
                                { value: 'mid', label: 'Mid' },
                                { value: 'far', label: 'Far' },
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={readings.data}
                    rowKey={(row) => row.id}
                    meta={readings.meta}
                    pageUrl={tracking.readings.index.url()}
                    queryParams={queryParams}
                    emptyTitle="No readings"
                    emptyDescription="No tag readings match these filters."
                />
            </SettingsPageShell>
        </>
    );
}
