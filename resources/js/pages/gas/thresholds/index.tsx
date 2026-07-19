import { Form, Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/ir4/panel';
import { Button } from '@/components/ui/button';
import type { GasThreshold } from '@/types/gas';

type Props = {
    thresholds: GasThreshold[];
    canManage: boolean;
};

export default function GasThresholdsIndex({ thresholds, canManage }: Props) {
    return (
        <>
            <Head title="Gas thresholds" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Gas &amp; CO₂</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Thresholds
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            Global installation thresholds (v1)
                        </p>
                    </div>
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/gas">Dashboard</Link>
                    </Button>
                </div>

                <Panel
                    title="Warning &amp; alarm levels"
                    subtitle="Applies to every device measuring each channel"
                >
                    {canManage ? (
                        <Form
                            action="/gas/thresholds"
                            method="put"
                            className="flex flex-col gap-4"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="overflow-hidden rounded-[var(--radius-sm)] border border-border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-surface-2 text-left">
                                                <tr>
                                                    <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                                        Gas
                                                    </th>
                                                    <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                                        Warning
                                                    </th>
                                                    <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                                        Alarm
                                                    </th>
                                                    <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                                        Unit
                                                    </th>
                                                    <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                                        Direction
                                                    </th>
                                                    <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                                        Last changed
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {thresholds.map(
                                                    (row, index) => (
                                                        <tr
                                                            key={row.gas_type}
                                                            className="border-t border-border"
                                                        >
                                                            <td className="px-3 py-2 font-medium text-text">
                                                                {row.label ??
                                                                    row.gas_type}
                                                                <input
                                                                    type="hidden"
                                                                    name={`thresholds[${index}][gas_type]`}
                                                                    value={
                                                                        row.gas_type
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <input
                                                                    type="number"
                                                                    step="any"
                                                                    name={`thresholds[${index}][warning_level]`}
                                                                    defaultValue={
                                                                        row.warning_level
                                                                    }
                                                                    className="h-9 w-28 rounded-[var(--radius-sm)] border border-input bg-background px-2 font-mono tabular-nums"
                                                                />
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <input
                                                                    type="number"
                                                                    step="any"
                                                                    name={`thresholds[${index}][alarm_level]`}
                                                                    defaultValue={
                                                                        row.alarm_level
                                                                    }
                                                                    className="h-9 w-28 rounded-[var(--radius-sm)] border border-input bg-background px-2 font-mono tabular-nums"
                                                                />
                                                            </td>
                                                            <td className="px-3 py-2 text-text-dim">
                                                                {row.unit}
                                                            </td>
                                                            <td className="px-3 py-2 text-text-dim">
                                                                {row.direction}
                                                            </td>
                                                            <td className="px-3 py-2 text-xs text-text-faint">
                                                                {row.updated_by_name ??
                                                                    '—'}
                                                                {row.updated_at
                                                                    ? ` · ${new Date(row.updated_at).toLocaleString()}`
                                                                    : ''}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="self-start"
                                    >
                                        Save thresholds
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <div className="overflow-hidden rounded-[var(--radius-sm)] border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-surface-2 text-left">
                                    <tr>
                                        <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                            Gas
                                        </th>
                                        <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                            Warning
                                        </th>
                                        <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                            Alarm
                                        </th>
                                        <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                            Unit
                                        </th>
                                        <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                            Direction
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {thresholds.map((row) => (
                                        <tr
                                            key={row.gas_type}
                                            className="border-t border-border"
                                        >
                                            <td className="px-3 py-2 font-medium text-text">
                                                {row.label ?? row.gas_type}
                                            </td>
                                            <td className="px-3 py-2 font-mono text-text-dim tabular-nums">
                                                {row.warning_level}
                                            </td>
                                            <td className="px-3 py-2 font-mono text-text-dim tabular-nums">
                                                {row.alarm_level}
                                            </td>
                                            <td className="px-3 py-2 text-text-dim">
                                                {row.unit}
                                            </td>
                                            <td className="px-3 py-2 text-text-dim">
                                                {row.direction}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}
