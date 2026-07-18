import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { ViolationTypeLabels } from '@/types/enums';
import type { PpeSummary } from '@/types/ppe';

type Props = {
    summary: PpeSummary;
    filters: { from: string; to: string };
    unreviewedCount: number;
    canExport: boolean;
};

export default function PpeTrendsIndex({
    summary,
    filters,
    unreviewedCount,
    canExport,
}: Props) {
    const maxType = Math.max(1, ...Object.values(summary.by_type));
    const maxHour = Math.max(1, ...summary.by_hour);

    return (
        <>
            <Head title="PPE Trends" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="PPE trends"
                        description={`${summary.total} counted · ${unreviewedCount} unreviewed · FP rate ${(summary.false_positive_rate * 100).toFixed(1)}%`}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="secondary" size="sm">
                            <Link href="/ppe/violations">Violations</Link>
                        </Button>
                        {canExport && (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => submitExport('csv', filters)}
                                >
                                    CSV
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => submitExport('pdf', filters)}
                                >
                                    PDF
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/ppe/trends',
                            {
                                from: String(form.get('from') ?? ''),
                                to: String(form.get('to') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
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

                <p className="text-sm text-muted-foreground">
                    Excluded false positives: {summary.excluded_false_positives}
                </p>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">By type</h2>
                    <div className="space-y-2">
                        {Object.entries(summary.by_type).map(
                            ([type, count]) => (
                                <div
                                    key={type}
                                    className="flex items-center gap-3 text-sm"
                                >
                                    <div className="w-36 shrink-0">
                                        {ViolationTypeLabels[
                                            type as keyof typeof ViolationTypeLabels
                                        ] ?? type}
                                    </div>
                                    <div className="h-3 flex-1 rounded bg-muted">
                                        <div
                                            className="h-3 rounded bg-primary"
                                            style={{
                                                width: `${(count / maxType) * 100}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="w-10 text-right tabular-nums">
                                        {count}
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">By hour</h2>
                    <div className="flex h-32 items-end gap-1">
                        {summary.by_hour.map((count, hour) => (
                            <div
                                key={hour}
                                className="flex-1 rounded-t bg-primary/80"
                                style={{
                                    height: `${(count / maxHour) * 100}%`,
                                    minHeight: count > 0 ? 4 : 0,
                                }}
                                title={`${hour}:00 — ${count}`}
                            />
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">By camera</h2>
                    <ul className="space-y-1 text-sm">
                        {summary.by_camera.map((row) => (
                            <li
                                key={row.camera_id}
                                className="flex justify-between border-b border-border py-2"
                            >
                                <span>
                                    {row.camera_ref ||
                                        `Camera #${row.camera_id}`}
                                </span>
                                <span className="tabular-nums">
                                    {row.count}
                                </span>
                            </li>
                        ))}
                        {summary.by_camera.length === 0 && (
                            <li className="text-muted-foreground">No data</li>
                        )}
                    </ul>
                </section>
            </div>
        </>
    );
}

function submitExport(
    format: 'csv' | 'pdf',
    filters: { from: string; to: string },
): void {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/ppe/violations/export';
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    form.innerHTML = `
        <input name="_token" value="${token ?? ''}" />
        <input name="format" value="${format}" />
        <input name="from" value="${filters.from}" />
        <input name="to" value="${filters.to}" />
    `;
    document.body.appendChild(form);
    form.submit();
    form.remove();
}
