import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { GasTrendSeries } from '@/types/gas';

type Props = {
    series: GasTrendSeries;
    filters: {
        gas_type: string;
        device_id: string;
        range: string;
        from: string;
        to: string;
    };
    devices: Array<{ id: number; name: string; reference: string }>;
    gasTypes: Array<{ value: string; label: string }>;
};

export default function GasTrends({
    series,
    filters,
    devices,
    gasTypes,
}: Props) {
    const max = Math.max(
        1,
        ...series.points.map((p) => Math.max(p.max ?? 0, p.value ?? 0)),
    );

    return (
        <>
            <Head title="Gas trends" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Gas trends"
                        description={`Source: ${series.source} · ${series.points.length} points`}
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
                            '/gas/trends',
                            {
                                gas_type: String(form.get('gas_type') ?? ''),
                                device_id: String(form.get('device_id') ?? ''),
                                range: String(form.get('range') ?? 'day'),
                                from: String(form.get('from') ?? ''),
                                to: String(form.get('to') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <select
                        name="gas_type"
                        defaultValue={filters.gas_type}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        {gasTypes.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="device_id"
                        defaultValue={filters.device_id}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All devices</option>
                        {devices.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="range"
                        defaultValue={filters.range}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="shift">Shift (12h)</option>
                        <option value="day">Day</option>
                        <option value="week">Week</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input
                        type="date"
                        name="from"
                        defaultValue={filters.from}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <input
                        type="date"
                        name="to"
                        defaultValue={filters.to}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <Button type="submit" size="sm">
                        Apply
                    </Button>
                </form>

                <div className="flex h-48 items-end gap-0.5 rounded-lg border border-border p-3">
                    {series.points.map((point, i) => (
                        <div
                            key={`${point.at}-${i}`}
                            className="flex-1 rounded-t bg-primary/70"
                            style={{
                                height: `${((point.avg ?? point.value ?? 0) / max) * 100}%`,
                                minHeight:
                                    (point.avg ?? point.value ?? 0) > 0 ? 2 : 0,
                            }}
                            title={`${point.at}: ${point.avg ?? point.value}`}
                        />
                    ))}
                    {series.points.length === 0 && (
                        <div className="flex w-full items-center justify-center text-sm text-muted-foreground">
                            No readings in range
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
