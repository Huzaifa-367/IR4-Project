import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
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
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Gas thresholds"
                        description="Global installation thresholds (v1)"
                    />
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/gas">Dashboard</Link>
                    </Button>
                </div>

                {canManage ? (
                    <Form
                        action="/gas/thresholds"
                        method="put"
                        className="space-y-4"
                    >
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="p-3">Gas</th>
                                        <th className="p-3">Warning</th>
                                        <th className="p-3">Alarm</th>
                                        <th className="p-3">Unit</th>
                                        <th className="p-3">Direction</th>
                                        <th className="p-3">Last changed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {thresholds.map((row, index) => (
                                        <tr
                                            key={row.gas_type}
                                            className="border-t border-border"
                                        >
                                            <td className="p-3">
                                                {row.label ?? row.gas_type}
                                                <input
                                                    type="hidden"
                                                    name={`thresholds[${index}][gas_type]`}
                                                    value={row.gas_type}
                                                />
                                            </td>
                                            <td className="p-3">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    name={`thresholds[${index}][warning_level]`}
                                                    defaultValue={
                                                        row.warning_level
                                                    }
                                                    className="h-9 w-28 rounded-md border border-input bg-background px-2"
                                                />
                                            </td>
                                            <td className="p-3">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    name={`thresholds[${index}][alarm_level]`}
                                                    defaultValue={
                                                        row.alarm_level
                                                    }
                                                    className="h-9 w-28 rounded-md border border-input bg-background px-2"
                                                />
                                            </td>
                                            <td className="p-3">{row.unit}</td>
                                            <td className="p-3">
                                                {row.direction}
                                            </td>
                                            <td className="p-3 text-xs text-muted-foreground">
                                                {row.updated_by_name ?? '—'}
                                                {row.updated_at
                                                    ? ` · ${new Date(row.updated_at).toLocaleString()}`
                                                    : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Button type="submit">Save thresholds</Button>
                    </Form>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="p-3">Gas</th>
                                    <th className="p-3">Warning</th>
                                    <th className="p-3">Alarm</th>
                                    <th className="p-3">Unit</th>
                                    <th className="p-3">Direction</th>
                                </tr>
                            </thead>
                            <tbody>
                                {thresholds.map((row) => (
                                    <tr
                                        key={row.gas_type}
                                        className="border-t border-border"
                                    >
                                        <td className="p-3">
                                            {row.label ?? row.gas_type}
                                        </td>
                                        <td className="p-3 tabular-nums">
                                            {row.warning_level}
                                        </td>
                                        <td className="p-3 tabular-nums">
                                            {row.alarm_level}
                                        </td>
                                        <td className="p-3">{row.unit}</td>
                                        <td className="p-3">{row.direction}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
