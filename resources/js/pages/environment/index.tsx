import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { EnvironmentTrendSeries } from '@/types/environment';

type Props = {
    series: EnvironmentTrendSeries;
    filters: {
        parameter: string;
        device_id: string;
        range: string;
        from: string;
        to: string;
    };
    devices: Array<{ id: number; name: string; reference: string }>;
    parameters: Array<{ value: string; label: string }>;
};

export default function EnvironmentTrends({
    series,
    filters,
    devices,
    parameters,
}: Props) {
    const maximum = Math.max(
        1,
        ...series.points.map((point) => point.max ?? point.value ?? 0),
    );

    return (
        <>
            <Head title="Environmental trends" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Environmental trends"
                        description={`${series.points.length} points · ${series.source}`}
                    />
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/dashboard">Dashboard</Link>
                    </Button>
                </div>
                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/environment',
                            {
                                parameter: String(form.get('parameter') ?? ''),
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
                        name="parameter"
                        defaultValue={filters.parameter}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        {parameters.map((parameter) => (
                            <option
                                key={parameter.value}
                                value={parameter.value}
                            >
                                {parameter.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="device_id"
                        defaultValue={filters.device_id}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All sensors</option>
                        {devices.map((device) => (
                            <option key={device.id} value={device.id}>
                                {device.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="range"
                        defaultValue={filters.range}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
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
                <div className="flex h-56 items-end gap-0.5 rounded-lg border border-border p-4">
                    {series.points.map((point, index) => (
                        <div
                            key={`${point.at}-${point.device_id}-${index}`}
                            className="flex-1 rounded-t bg-sky-500/75"
                            style={{
                                height: `${((point.avg ?? point.value ?? 0) / maximum) * 100}%`,
                                minHeight:
                                    (point.avg ?? point.value ?? 0) > 0 ? 2 : 0,
                            }}
                            title={`${new Date(point.at).toLocaleString()}: ${point.avg ?? point.value}`}
                        />
                    ))}
                    {series.points.length === 0 && (
                        <div className="flex size-full items-center justify-center text-sm text-muted-foreground">
                            No readings in range
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
