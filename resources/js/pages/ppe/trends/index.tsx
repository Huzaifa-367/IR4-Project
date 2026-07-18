import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { BarChart } from '@/components/ir4/bar-chart';
import { HorizontalBars } from '@/components/ir4/horizontal-bars';
import { Panel } from '@/components/ir4/panel';
import { StatCard } from '@/components/ir4/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    const byType = useMemo(
        () =>
            Object.entries(summary.by_type).map(([type, count]) => ({
                label:
                    ViolationTypeLabels[
                        type as keyof typeof ViolationTypeLabels
                    ] ?? type,
                value: count,
            })),
        [summary.by_type],
    );

    const byHour = useMemo(
        () =>
            summary.by_hour.map((count, hour) => ({
                label: `${hour}:00`,
                value: count,
            })),
        [summary.by_hour],
    );

    const byCamera = useMemo(
        () =>
            summary.by_camera.map((row) => ({
                label: row.camera_ref || `Camera #${row.camera_id}`,
                value: row.count,
            })),
        [summary.by_camera],
    );

    function applyFilters(patch: Partial<Props['filters']>): void {
        router.get(
            '/ppe/trends',
            { from: patch.from ?? filters.from, to: patch.to ?? filters.to },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="PPE Trends" />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Heading
                            title="PPE Trends"
                            description="Density heatmap, false-positive rate, and per-camera breakdown."
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
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

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-text-faint"
                            htmlFor="from"
                        >
                            From
                        </label>
                        <Input
                            id="from"
                            type="date"
                            defaultValue={filters.from}
                            onChange={(event) =>
                                applyFilters({ from: event.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs text-text-faint" htmlFor="to">
                            To
                        </label>
                        <Input
                            id="to"
                            type="date"
                            defaultValue={filters.to}
                            onChange={(event) =>
                                applyFilters({ to: event.target.value })
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total violations" value={summary.total} />
                    <StatCard label="Unreviewed" value={unreviewedCount} />
                    <StatCard
                        label="False-positive rate"
                        value={`${(summary.false_positive_rate * 100).toFixed(1)}%`}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel
                        title="Violations by type"
                        subtitle={`${summary.excluded_false_positives} false positives excluded`}
                        className="xl:col-span-5"
                    >
                        <HorizontalBars items={byType} />
                    </Panel>
                    <Panel
                        title="Violations by hour"
                        subtitle="density across the day"
                        className="xl:col-span-7"
                    >
                        <BarChart data={byHour} height={200} />
                    </Panel>
                </div>

                <Panel title="Violations by camera" subtitle="this range">
                    <HorizontalBars items={byCamera} />
                </Panel>
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
