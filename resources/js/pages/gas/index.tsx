import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { GasChannelGauges } from '@/components/ir4/gas-channel-gauges';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
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

type ChannelStatus = 'ok' | 'warn' | 'crit';

function channelStatus(
    value: number | null,
    gasKey: string,
    thresholds: Record<string, GasThreshold>,
): ChannelStatus {
    if (value === null) {
        return 'ok';
    }

    const t = thresholds[gasKey];

    if (!t) {
        return 'ok';
    }

    if (t.direction === 'below') {
        if (value <= t.alarm_level) {
            return 'crit';
        }

        if (value <= t.warning_level) {
            return 'warn';
        }

        return 'ok';
    }

    if (value >= t.alarm_level) {
        return 'crit';
    }

    if (value >= t.warning_level) {
        return 'warn';
    }

    return 'ok';
}

function worstOf(a: ChannelStatus, b: ChannelStatus): ChannelStatus {
    if (a === 'crit' || b === 'crit') {
        return 'crit';
    }

    if (a === 'warn' || b === 'warn') {
        return 'warn';
    }

    return 'ok';
}

function panelGauges(
    panel: GasLivePanel,
    thresholds: Record<string, GasThreshold>,
): Array<{
    label: string;
    source: string;
    value: number;
    unit: string;
    warn: number | null;
    alarm: number | null;
    status: ChannelStatus;
}> {
    const gauges = [];

    if (panel.lel_pct !== null) {
        gauges.push({
            label: 'LEL',
            source: '',
            value: panel.lel_pct,
            unit: '%',
            warn: thresholds.lel?.warning_level ?? null,
            alarm: thresholds.lel?.alarm_level ?? null,
            status: channelStatus(panel.lel_pct, 'lel', thresholds),
        });
    }

    if (panel.h2s_ppm !== null) {
        gauges.push({
            label: 'H₂S',
            source: '',
            value: panel.h2s_ppm,
            unit: 'ppm',
            warn: thresholds.h2s?.warning_level ?? null,
            alarm: thresholds.h2s?.alarm_level ?? null,
            status: channelStatus(panel.h2s_ppm, 'h2s', thresholds),
        });
    }

    if (panel.o2_pct !== null) {
        gauges.push({
            label: 'O₂',
            source: '',
            value: panel.o2_pct,
            unit: '%vol',
            warn: thresholds.o2_low?.warning_level ?? null,
            alarm: thresholds.o2_low?.alarm_level ?? null,
            status: worstOf(
                channelStatus(panel.o2_pct, 'o2_low', thresholds),
                channelStatus(panel.o2_pct, 'o2_high', thresholds),
            ),
        });
    }

    if (panel.co_ppm !== null) {
        gauges.push({
            label: 'CO',
            source: '',
            value: panel.co_ppm,
            unit: 'ppm',
            warn: thresholds.co?.warning_level ?? null,
            alarm: thresholds.co?.alarm_level ?? null,
            status: channelStatus(panel.co_ppm, 'co', thresholds),
        });
    }

    if (panel.co2_ppm !== null) {
        gauges.push({
            label: 'CO₂',
            source: '',
            value: panel.co2_ppm,
            unit: 'ppm',
            warn: thresholds.co2?.warning_level ?? null,
            alarm: thresholds.co2?.alarm_level ?? null,
            status: channelStatus(panel.co2_ppm, 'co2', thresholds),
        });
    }

    return gauges;
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
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
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
                    <div className="flex items-center gap-2 rounded-[var(--radius-sm)] border border-[color:var(--crit)]/40 bg-[color:var(--crit-bg)] px-4 py-3 text-sm text-[color:var(--crit)]">
                        {openAlarmCount} open gas alarm
                        {openAlarmCount === 1 ? '' : 's'} — check device panels
                        below.
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {panels.map((panel) => (
                        <Panel
                            key={panel.device_id}
                            title={panel.device_name}
                            subtitle={
                                panel.device_ref +
                                (panel.asset_label
                                    ? ` · ${panel.asset_label}`
                                    : '')
                            }
                            action={
                                <StatusPill
                                    label={panel.is_stale ? 'Stale' : 'Live'}
                                    tone={panel.is_stale ? 'warn' : 'ok'}
                                />
                            }
                        >
                            <GasChannelGauges
                                gauges={panelGauges(panel, thresholds)}
                            />
                            {panel.open_alarms.length > 0 && (
                                <ul className="mt-3 space-y-1 text-xs text-[color:var(--crit)]">
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
                        </Panel>
                    ))}
                    {panels.length === 0 && (
                        <div className="col-span-full rounded-[var(--radius)] border border-dashed border-border p-8 text-center text-text-faint">
                            No gas or CO₂ detectors registered
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
