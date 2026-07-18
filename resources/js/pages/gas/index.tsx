import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Button } from '@/components/ui/button';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { GasTypeLabels } from '@/types/enums';
import type { GasLivePanel, GasThreshold } from '@/types/gas';

type Props = {
    panels: GasLivePanel[];
    thresholds: Record<string, GasThreshold>;
    canManageThresholds: boolean;
    canAcknowledge: boolean;
};

function channelColor(
    value: number | null,
    gasKey: string,
    thresholds: Record<string, GasThreshold>,
): string {
    if (value === null) {
        return 'text-muted-foreground';
    }

    const t = thresholds[gasKey];

    if (!t) {
        return 'text-foreground';
    }

    if (t.direction === 'below') {
        if (value <= t.alarm_level) {
            return 'text-red-600';
        }

        if (value <= t.warning_level) {
            return 'text-amber-600';
        }

        return 'text-emerald-600';
    }

    if (value >= t.alarm_level) {
        return 'text-red-600';
    }

    if (value >= t.warning_level) {
        return 'text-amber-600';
    }

    return 'text-emerald-600';
}

export default function GasDashboard({
    panels: initial,
    thresholds,
    canManageThresholds,
}: Props) {
    const [panels, setPanels] = useState(initial);

    const { status } = useReverbChannel({
        channel: 'gas',
        events: ['.GasLiveUpdated'],
        onEvent: (payload: unknown) => {
            const p = payload as { panel: GasLivePanel };
            setPanels((prev) => {
                const next = prev.filter(
                    (x) => x.device_id !== p.panel.device_id,
                );

                return [...next, p.panel].sort((a, b) =>
                    a.device_name.localeCompare(b.device_name),
                );
            });
        },
        snapshotUrl: '/gas/api/live',
        onSnapshot: (data) => {
            const json = data as { data: { panels: GasLivePanel[] } };
            setPanels(json.data.panels);
        },
        pollIntervalMs: 15_000,
    });

    const openAlarmCount = panels.reduce((n, p) => n + p.open_alarms.length, 0);

    return (
        <>
            <Head title="Gas & CO₂" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Gas & CO₂"
                        description={`${panels.length} detectors · ${openAlarmCount} open alarms`}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <LiveStatusPill status={status} />
                        <Button asChild size="sm" variant="secondary">
                            <Link href="/gas/alarms">Alarms</Link>
                        </Button>
                        <Button asChild size="sm" variant="secondary">
                            <Link href="/gas/trends">Trends</Link>
                        </Button>
                        {canManageThresholds && (
                            <Button asChild size="sm" variant="outline">
                                <Link href="/gas/thresholds">Thresholds</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {openAlarmCount > 0 && (
                    <div className="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                        {openAlarmCount} open gas alarm
                        {openAlarmCount === 1 ? '' : 's'} — check device panels
                        below.
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {panels.map((panel) => (
                        <div
                            key={panel.device_id}
                            className="space-y-3 rounded-lg border border-border p-4"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <div className="font-medium">
                                        {panel.device_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {panel.device_ref}
                                        {panel.asset_label
                                            ? ` · ${panel.asset_label}`
                                            : ''}
                                    </div>
                                </div>
                                {panel.is_stale ? (
                                    <span className="rounded bg-amber-500/15 px-2 py-0.5 text-xs text-amber-700">
                                        Stale
                                    </span>
                                ) : (
                                    <span className="rounded bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-700">
                                        Live
                                    </span>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-2 text-sm">
                                <Gauge
                                    label="LEL %"
                                    value={panel.lel_pct}
                                    className={channelColor(
                                        panel.lel_pct,
                                        'lel',
                                        thresholds,
                                    )}
                                />
                                <Gauge
                                    label="H₂S ppm"
                                    value={panel.h2s_ppm}
                                    className={channelColor(
                                        panel.h2s_ppm,
                                        'h2s',
                                        thresholds,
                                    )}
                                />
                                <Gauge
                                    label="O₂ %"
                                    value={panel.o2_pct}
                                    className={
                                        panel.o2_pct !== null &&
                                        (channelColor(
                                            panel.o2_pct,
                                            'o2_low',
                                            thresholds,
                                        ) === 'text-red-600' ||
                                            channelColor(
                                                panel.o2_pct,
                                                'o2_high',
                                                thresholds,
                                            ) === 'text-red-600')
                                            ? 'text-red-600'
                                            : channelColor(
                                                    panel.o2_pct,
                                                    'o2_low',
                                                    thresholds,
                                                ) === 'text-amber-600' ||
                                                channelColor(
                                                    panel.o2_pct,
                                                    'o2_high',
                                                    thresholds,
                                                ) === 'text-amber-600'
                                              ? 'text-amber-600'
                                              : channelColor(
                                                    panel.o2_pct,
                                                    'o2_low',
                                                    thresholds,
                                                )
                                    }
                                />
                                <Gauge
                                    label="CO ppm"
                                    value={panel.co_ppm}
                                    className={channelColor(
                                        panel.co_ppm,
                                        'co',
                                        thresholds,
                                    )}
                                />
                            </div>
                            <div className="rounded border border-border/60 p-2">
                                <div className="text-xs text-muted-foreground">
                                    CO₂ ppm
                                </div>
                                <div
                                    className={`text-2xl font-semibold tabular-nums ${channelColor(panel.co2_ppm, 'co2', thresholds)}`}
                                >
                                    {panel.co2_ppm ?? '—'}
                                </div>
                            </div>
                            {panel.open_alarms.length > 0 && (
                                <ul className="space-y-1 text-xs text-red-600">
                                    {panel.open_alarms.map((a) => (
                                        <li key={`${a.gas_type}-${a.level}`}>
                                            {GasTypeLabels[
                                                a.gas_type as keyof typeof GasTypeLabels
                                            ] ?? a.gas_type}{' '}
                                            {a.level}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ))}
                    {panels.length === 0 && (
                        <div className="col-span-full rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground">
                            No gas or CO₂ detectors registered
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function Gauge({
    label,
    value,
    className,
}: {
    label: string;
    value: number | null;
    className: string;
}) {
    return (
        <div className="rounded border border-border/60 p-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className={`text-xl font-semibold tabular-nums ${className}`}>
                {value ?? '—'}
            </div>
        </div>
    );
}
