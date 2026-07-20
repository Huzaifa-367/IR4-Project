import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { visitFilters } from '@/lib/visit-filters';
import { GasTypeLabels } from '@/types/enums';
import type { GasAlarm } from '@/types/gas';
import type { PaginatedMeta } from '@/types/hardware';

type Props = {
    alarms: { data: GasAlarm[]; meta: PaginatedMeta };
    filters: {
        gas_type: string;
        level: string;
        device_id: string;
        resolved: string;
    };
    canAcknowledge: boolean;
};

const ALL = 'all';

export default function GasAlarmsIndex({
    alarms,
    filters,
    canAcknowledge,
}: Props) {
    const [gasType, setGasType] = useState(filters.gas_type || ALL);
    const [level, setLevel] = useState(filters.level || ALL);
    const [resolved, setResolved] = useState(filters.resolved || ALL);

    function applyFilters(
        patch: Partial<{
            gas_type: string;
            level: string;
            resolved: string;
        }> = {},
    ): void {
        const nextGasType = patch.gas_type ?? gasType;
        const nextLevel = patch.level ?? level;
        const nextResolved = patch.resolved ?? resolved;

        visitFilters('/gas/alarms', {
            gas_type: nextGasType === ALL ? undefined : nextGasType,
            level: nextLevel === ALL ? undefined : nextLevel,
            resolved: nextResolved === ALL ? undefined : nextResolved,
        });
    }

    const queryParams = {
        gas_type: gasType === ALL ? undefined : gasType,
        level: level === ALL ? undefined : level,
        resolved: resolved === ALL ? undefined : resolved,
    };

    const columns: SettingsColumn<GasAlarm>[] = [
        {
            key: 'when',
            header: 'When',
            cell: (row) => new Date(row.triggered_at).toLocaleString(),
        },
        { key: 'device', header: 'Device', cell: (row) => row.device_name },
        {
            key: 'gas',
            header: 'Gas',
            cell: (row) =>
                GasTypeLabels[row.gas_type as keyof typeof GasTypeLabels] ??
                row.gas_type,
        },
        {
            key: 'level',
            header: 'Level',
            cell: (row) => (
                <StatusPill
                    label={row.level}
                    tone={row.level === 'alarm' ? 'crit' : 'warn'}
                />
            ),
        },
        {
            key: 'reading',
            header: 'Reading',
            className: 'text-right font-mono tabular-nums',
            cell: (row) => row.reading_value,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    <StatusPill
                        label={row.is_open ? 'Open' : 'Resolved'}
                        tone={row.is_open ? 'crit' : 'ok'}
                    />
                    {row.during_outage ? (
                        <StatusPill label="Outage" tone="neutral" />
                    ) : null}
                    {row.acknowledged_at ? (
                        <StatusPill label="Ack" tone="accent" />
                    ) : null}
                </div>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24 text-right',
            cell: (row) =>
                canAcknowledge && row.is_open && !row.acknowledged_at ? (
                    <Form
                        action={`/gas/alarms/${row.id}/acknowledge`}
                        method="post"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                Acknowledge
                            </Button>
                        )}
                    </Form>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Gas alarms" />
            <SettingsPageShell
                title="Gas Alarms"
                description={`${alarms.meta.total} records`}
                actions={
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/gas">Dashboard</Link>
                    </Button>
                }
                filters={
                    <>
                        <SearchableSelect
                            value={resolved}
                            onValueChange={(value) => {
                                setResolved(value);
                                applyFilters({ resolved: value });
                            }}
                            placeholder="Status"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All' },
                                { value: 'open', label: 'Open' },
                                { value: 'resolved', label: 'Resolved' },
                            ]}
                        />
                        <SearchableSelect
                            value={level}
                            onValueChange={(value) => {
                                setLevel(value);
                                applyFilters({ level: value });
                            }}
                            placeholder="Level"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All levels' },
                                { value: 'warning', label: 'Warning' },
                                { value: 'alarm', label: 'Alarm' },
                            ]}
                        />
                        <SearchableSelect
                            value={gasType}
                            onValueChange={(value) => {
                                setGasType(value);
                                applyFilters({ gas_type: value });
                            }}
                            placeholder="Gas"
                            triggerClassName="w-32"
                            options={[
                                { value: ALL, label: 'All gases' },
                                ...Object.entries(GasTypeLabels).map(
                                    ([value, label]) => ({
                                        value,
                                        label,
                                    }),
                                ),
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={alarms.data}
                    rowKey={(row) => row.id}
                    meta={alarms.meta}
                    pageUrl="/gas/alarms"
                    queryParams={queryParams}
                    emptyTitle="No alarms"
                    emptyDescription="No gas alarms match these filters."
                />
            </SettingsPageShell>
        </>
    );
}
