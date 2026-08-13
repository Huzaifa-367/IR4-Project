import { cn } from '@/lib/utils';

type Gauge = {
    label: string;
    source?: string;
    value: number;
    unit: string;
    warn: number | null;
    alarm: number | null;
    highWarn?: number | null;
    highAlarm?: number | null;
    direction?: 'above' | 'below';
    status: 'ok' | 'warn' | 'crit';
};

type Props = {
    gauges: Gauge[];
    className?: string;
};

function formatTick(value: number): string {
    if (value >= 100) {
        return String(Math.round(value));
    }

    if (Number.isInteger(value)) {
        return String(value);
    }

    return value.toFixed(1);
}

function scaleMax(gauge: Gauge): number {
    if ((gauge.direction ?? 'above') === 'below') {
        return Math.max(
            gauge.value,
            gauge.highAlarm ?? 0,
            gauge.highWarn ?? 0,
            25,
        );
    }

    return Math.max(
        gauge.value,
        gauge.alarm ?? 0,
        gauge.warn ?? 0,
        gauge.value > 0 ? gauge.value : 1,
    );
}

function markerPercent(value: number | null, max: number): number | null {
    if (value === null || max <= 0) {
        return null;
    }

    return Math.max(0, Math.min(100, (value / max) * 100));
}

export function GasChannelGauges({ gauges, className }: Props) {
    if (gauges.length === 0) {
        return (
            <div className="py-8 text-center text-sm text-text-faint">
                No live gas channels
            </div>
        );
    }

    return (
        <div className={cn('space-y-2.5', className)}>
            {gauges.map((gauge, index) => {
                const max = scaleMax(gauge);
                const fillPct =
                    gauge.value <= 0
                        ? 0
                        : Math.max(0, Math.min(100, (gauge.value / max) * 100));
                const warnPct = markerPercent(gauge.warn, max);
                const alarmPct = markerPercent(gauge.alarm, max);
                const highWarnPct = markerPercent(gauge.highWarn ?? null, max);
                const highAlarmPct = markerPercent(
                    gauge.highAlarm ?? null,
                    max,
                );
                const color =
                    gauge.status === 'crit'
                        ? 'var(--crit)'
                        : gauge.status === 'warn'
                          ? 'var(--warn)'
                          : 'var(--ok)';
                const source = gauge.source?.trim() ?? '';
                const key = `${gauge.label}-${source || index}`;

                return (
                    <div
                        key={key}
                        className="grid grid-cols-[2.75rem_minmax(0,1fr)_4.75rem] items-center gap-x-2"
                    >
                        <div className="min-w-0">
                            <div className="text-xs font-semibold text-text">
                                {gauge.label}
                            </div>
                            {source !== '' ? (
                                <div className="truncate text-[10px] text-text-faint">
                                    {source}
                                </div>
                            ) : null}
                        </div>
                        <div className="min-w-0">
                            <div className="relative h-1.5 rounded-pill bg-surface-3">
                                {fillPct > 0 ? (
                                    <div
                                        className="absolute inset-y-0 left-0 rounded-pill"
                                        style={{
                                            width: `${fillPct}%`,
                                            background: color,
                                        }}
                                    />
                                ) : null}
                                {warnPct !== null ? (
                                    <div
                                        className="absolute top-[-3px] bottom-[-3px] w-px bg-[color:var(--warn)]"
                                        style={{ left: `${warnPct}%` }}
                                    />
                                ) : null}
                                {alarmPct !== null ? (
                                    <div
                                        className="absolute top-[-3px] bottom-[-3px] w-px bg-[color:var(--crit)]"
                                        style={{ left: `${alarmPct}%` }}
                                    />
                                ) : null}
                                {highWarnPct !== null ? (
                                    <div
                                        className="absolute top-[-3px] bottom-[-3px] w-px bg-[color:var(--warn)]"
                                        style={{ left: `${highWarnPct}%` }}
                                    />
                                ) : null}
                                {highAlarmPct !== null ? (
                                    <div
                                        className="absolute top-[-3px] bottom-[-3px] w-px bg-[color:var(--crit)]"
                                        style={{ left: `${highAlarmPct}%` }}
                                    />
                                ) : null}
                            </div>
                            <div className="relative mt-0.5 h-3 text-[9px] leading-3 text-text-faint tabular-nums">
                                <span className="absolute left-0">0</span>
                                {warnPct !== null &&
                                warnPct > 12 &&
                                warnPct < 88 ? (
                                    <span
                                        className="absolute -translate-x-1/2"
                                        style={{ left: `${warnPct}%` }}
                                    >
                                        {formatTick(gauge.warn ?? 0)}
                                    </span>
                                ) : null}
                                {alarmPct !== null &&
                                alarmPct > 12 &&
                                alarmPct < 88 ? (
                                    <span
                                        className="absolute -translate-x-1/2 text-[color:var(--crit)]"
                                        style={{ left: `${alarmPct}%` }}
                                    >
                                        {formatTick(gauge.alarm ?? 0)}
                                    </span>
                                ) : null}
                                <span className="absolute right-0">
                                    {formatTick(max)}
                                </span>
                            </div>
                        </div>
                        <div className="text-right">
                            <span
                                className="font-mono text-sm font-semibold tabular-nums"
                                style={{ color }}
                            >
                                {Number.isInteger(gauge.value)
                                    ? gauge.value
                                    : gauge.value.toFixed(1)}
                            </span>
                            <span className="ml-0.5 text-[10px] text-text-faint">
                                {gauge.unit}
                            </span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
