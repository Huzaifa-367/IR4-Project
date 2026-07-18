import { Form, Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { GasTypeLabels } from '@/types/enums';
import type { GasAlarm } from '@/types/gas';

type Props = {
    alarms: {
        data: GasAlarm[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: {
        gas_type: string;
        level: string;
        device_id: string;
        resolved: string;
    };
    canAcknowledge: boolean;
};

export default function GasAlarmsIndex({
    alarms,
    filters,
    canAcknowledge,
}: Props) {
    return (
        <>
            <Head title="Gas alarms" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Gas alarms"
                        description={`${alarms.meta.total} records`}
                    />
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/gas">Dashboard</Link>
                    </Button>
                </div>

                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/gas/alarms',
                            {
                                gas_type: String(form.get('gas_type') ?? ''),
                                level: String(form.get('level') ?? ''),
                                resolved: String(form.get('resolved') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <select
                        name="resolved"
                        defaultValue={filters.resolved}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All</option>
                        <option value="open">Open</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <select
                        name="level"
                        defaultValue={filters.level}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All levels</option>
                        <option value="warning">Warning</option>
                        <option value="alarm">Alarm</option>
                    </select>
                    <select
                        name="gas_type"
                        defaultValue={filters.gas_type}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All gases</option>
                        {Object.entries(GasTypeLabels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" size="sm">
                        Filter
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="p-3">When</th>
                                <th className="p-3">Device</th>
                                <th className="p-3">Gas</th>
                                <th className="p-3">Level</th>
                                <th className="p-3">Reading</th>
                                <th className="p-3">Status</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {alarms.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-border"
                                >
                                    <td className="p-3">
                                        {new Date(
                                            row.triggered_at,
                                        ).toLocaleString()}
                                    </td>
                                    <td className="p-3">{row.device_name}</td>
                                    <td className="p-3">
                                        {GasTypeLabels[
                                            row.gas_type as keyof typeof GasTypeLabels
                                        ] ?? row.gas_type}
                                    </td>
                                    <td className="p-3">{row.level}</td>
                                    <td className="p-3 tabular-nums">
                                        {row.reading_value}
                                    </td>
                                    <td className="p-3">
                                        {row.is_open ? 'Open' : 'Resolved'}
                                        {row.during_outage ? ' · outage' : ''}
                                        {row.acknowledged_at ? ' · ack' : ''}
                                    </td>
                                    <td className="p-3 text-right">
                                        {canAcknowledge &&
                                            row.is_open &&
                                            !row.acknowledged_at && (
                                                <Form
                                                    action={`/gas/alarms/${row.id}/acknowledge`}
                                                    method="post"
                                                >
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                    >
                                                        Acknowledge
                                                    </Button>
                                                </Form>
                                            )}
                                    </td>
                                </tr>
                            ))}
                            {alarms.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No alarms
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
