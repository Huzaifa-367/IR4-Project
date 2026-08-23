import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import gas from '@/routes/gas';
import type { GasThreshold } from '@/types/gas';

type Props = {
    thresholds: GasThreshold[];
    canManage: boolean;
};

export function GasThresholdsEditor({ thresholds, canManage }: Props) {
    return (
        <section className="flex flex-col gap-4 rounded-[var(--radius-sm)] border border-border bg-surface p-5 shadow-[var(--shadow-card)]">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="eyebrow">gas_thresholds</p>
                    <h2 className="font-display text-base font-semibold tracking-tight text-text">
                        Warning &amp; alarm levels
                    </h2>
                    <p className="mt-0.5 text-xs text-text-dim">
                        Applies to every device measuring each channel
                    </p>
                </div>
            </div>

            {canManage ? (
                <Form
                    action={gas.thresholds.update.url()}
                    method="put"
                    className="flex flex-col gap-4"
                >
                    {({ processing }) => (
                        <>
                            <ThresholdTable thresholds={thresholds} editable />
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
                <ThresholdTable thresholds={thresholds} editable={false} />
            )}
        </section>
    );
}

function ThresholdTable({
    thresholds,
    editable,
}: {
    thresholds: GasThreshold[];
    editable: boolean;
}) {
    return (
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
                        {editable ? (
                            <th className="px-3 py-2 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                Last changed
                            </th>
                        ) : null}
                    </tr>
                </thead>
                <tbody>
                    {thresholds.map((row, index) => (
                        <tr
                            key={row.gas_type}
                            className="border-t border-border"
                        >
                            <td className="px-3 py-2 font-medium text-text">
                                {row.label ?? row.gas_type}
                                {editable ? (
                                    <input
                                        type="hidden"
                                        name={`thresholds[${index}][gas_type]`}
                                        value={row.gas_type}
                                    />
                                ) : null}
                            </td>
                            <td className="px-3 py-2">
                                {editable ? (
                                    <input
                                        type="number"
                                        step="any"
                                        name={`thresholds[${index}][warning_level]`}
                                        defaultValue={row.warning_level}
                                        className="h-9 w-28 rounded-[var(--radius-sm)] border border-input bg-background px-2 font-mono tabular-nums"
                                    />
                                ) : (
                                    <span className="font-mono text-text-dim tabular-nums">
                                        {row.warning_level}
                                    </span>
                                )}
                            </td>
                            <td className="px-3 py-2">
                                {editable ? (
                                    <input
                                        type="number"
                                        step="any"
                                        name={`thresholds[${index}][alarm_level]`}
                                        defaultValue={row.alarm_level}
                                        className="h-9 w-28 rounded-[var(--radius-sm)] border border-input bg-background px-2 font-mono tabular-nums"
                                    />
                                ) : (
                                    <span className="font-mono text-text-dim tabular-nums">
                                        {row.alarm_level}
                                    </span>
                                )}
                            </td>
                            <td className="px-3 py-2 text-text-dim">
                                {row.unit}
                            </td>
                            <td className="px-3 py-2 text-text-dim">
                                {row.direction}
                            </td>
                            {editable ? (
                                <td className="px-3 py-2 text-xs text-text-faint">
                                    {row.updated_by_name ?? '—'}
                                    {row.updated_at
                                        ? ` · ${new Date(row.updated_at).toLocaleString()}`
                                        : ''}
                                </td>
                            ) : null}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
