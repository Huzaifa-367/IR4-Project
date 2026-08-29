import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { DetailField, FactTile } from '@/components/ir4/fact-tile';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    avgOf,
    byTypeSummary,
    cameraRefLabel,
    deviceRefLabel,
    formatDate,
    formatDateCompact,
    formatDateTime,
    formatMinAvgMax,
    formatNumber,
    labelize,
    maxOf,
    mergeCounts,
    pluralize,
    sumBy,
} from '@/lib/report-format';
import { cn } from '@/lib/utils';
import reports from '@/routes/reports';
import weeklyReports from '@/routes/weekly-reports';
import type { WeeklyReport, WeeklyReportData } from '@/types/report';

type Props = {
    report: WeeklyReport;
    badges: Record<string, string>;
    canPublish: boolean;
};

type SectionDef = {
    key: keyof WeeklyReportData;
    title: string;
    short: string;
    blurb: string;
};

const sectionOrder: SectionDef[] = [
    {
        key: 'i_daily_safety_observations',
        title: 'i. Daily Safety Observations',
        short: 'Safety observations',
        blurb: 'Confirmed PPE detections by day and camera.',
    },
    {
        key: 'ii_hse_incidents',
        title: 'ii. HSE Accidents & Incidents',
        short: 'HSE incidents',
        blurb: 'Operator-logged accidents and incidents for this week, with actions taken.',
    },
    {
        key: 'iii_lsr_violations',
        title: 'iii. LSR Violations & Actions Taken',
        short: 'LSR violations',
        blurb: 'Life-Saving Rule breaches and the corrective action recorded for each.',
    },
    {
        key: 'iv_weather',
        title: 'iv. Weather Conditions',
        short: 'Weather',
        blurb: 'Daily temperature and humidity from the site weather feed.',
    },
    {
        key: 'v_manpower',
        title: 'v. Site Manpower',
        short: 'Manpower',
        blurb: 'Peak and average people on site for each day.',
    },
    {
        key: 'vi_units_monitored',
        title: 'vi. Total Vehicles/Units Monitored',
        short: 'Units monitored',
        blurb: 'How many field poles/units were actively monitored this week.',
    },
    {
        key: 'vii_vehicle_violations',
        title: 'vii. Vehicle Violations & Actions Taken',
        short: 'Vehicle violations',
        blurb: 'Manually logged vehicle violations and follow-up actions.',
    },
    {
        key: 'ix_gas',
        // Display viii: DOC item viii (environmental) is not shipped yet — keep freeze key ix_gas.
        title: 'viii. Gas Monitoring (LEL / H₂S / O₂ / CO / CO₂)',
        short: 'Gas',
        blurb: 'Daily gas channel ranges and alarm events. Cells show min / avg / max.',
    },
];

function statusTone(status: string): StatusPillTone {
    if (status === 'published') {
        return 'ok';
    }

    if (status === 'generated') {
        return 'accent';
    }

    return 'warn';
}

function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === 'object'
        ? (value as Record<string, unknown>)
        : {};
}

function str(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function num(value: unknown): number | null {
    return typeof value === 'number' && !Number.isNaN(value) ? value : null;
}

function rangeLabel(
    min: number | null,
    avg: number | null,
    max: number | null,
    unit = '',
): string {
    if (min === null && avg === null && max === null) {
        return 'No data';
    }

    const suffix = unit ? ` ${unit}` : '';

    return `${formatNumber(min)}${suffix} – ${formatNumber(avg)}${suffix} – ${formatNumber(max)}${suffix}`;
}

function EmptyState({
    label = 'No records in this period.',
    hint,
}: {
    label?: string;
    hint?: string;
}) {
    return (
        <div className="rounded-md border border-dashed border-border bg-surface-2/30 px-3 py-6 text-center">
            <p className="text-sm text-text-dim">{label}</p>
            {hint ? (
                <p className="mt-1 text-xs text-text-dim/80">{hint}</p>
            ) : null}
        </div>
    );
}

function SectionIntro({ text }: { text: string }) {
    return <p className="mb-3 text-sm leading-relaxed text-text-dim">{text}</p>;
}

/** Items that carry a coverage gap — never list every device %. */
function coverageGapItems(
    notes: WeeklyReportData['completeness']['notes'],
): Set<string> {
    return new Set(notes.map((note) => note.item));
}

/**
 * One short banner per section. New generates store a single aggregated note;
 * older frozen reports may still have many device rows — collapse those.
 */
function sectionCoverageMessage(
    notes: WeeklyReportData['completeness']['notes'],
): string | null {
    if (notes.length === 0) {
        return null;
    }

    if (notes.length === 1) {
        return notes[0].message;
    }

    return 'Coverage incomplete — some sensors for this item were offline more than 20% of the week. Treat figures with care.';
}

function buildExecutiveLines(data: WeeklyReportData): string[] {
    const lines: string[] = [];
    const ppeDays = data.i_daily_safety_observations?.per_day ?? [];
    const ppeTotal = sumBy(ppeDays, (row) => row.total);
    const incidents = data.ii_hse_incidents ?? [];
    const lsr = data.iii_lsr_violations?.entries ?? [];
    const alarms = data.ix_gas?.alarm_events ?? [];
    const vehicles = data.vii_vehicle_violations ?? [];
    const gapItems = coverageGapItems(data.completeness?.notes ?? []);
    const manpowerDays = data.v_manpower?.per_day ?? [];
    const peakManpower = maxOf(
        manpowerDays.map((day) => num(asRecord(day).peak)),
    );
    const weatherHasData = (data.iv_weather?.per_day ?? []).some((day) => {
        const temp = asRecord(asRecord(day).temp);

        return typeof temp.avg === 'number';
    });

    if (ppeTotal > 0) {
        lines.push(`${pluralize(ppeTotal, 'confirmed PPE observation')}.`);
    } else {
        lines.push('No confirmed PPE observations this week.');
    }

    lines.push(
        `${pluralize(incidents.length, 'HSE incident')}, ${pluralize(lsr.length, 'LSR violation')}, and ${pluralize(vehicles.length, 'vehicle violation')} logged by operators.`,
    );

    if (alarms.length > 0) {
        lines.push(
            `${pluralize(alarms.length, 'gas alarm event')} — open Gas Monitoring for details.`,
        );
    } else {
        lines.push('No gas alarm events in this period.');
    }

    if (peakManpower !== null && peakManpower > 0) {
        lines.push(
            `Peak site manpower reached ${formatNumber(peakManpower, 0)}.`,
        );
    } else {
        lines.push(
            'Site headcount stayed at zero — check Manpower coverage notes.',
        );
    }

    if (!weatherHasData) {
        lines.push('Weather samples were unavailable for this week.');
    }

    if (gapItems.size > 0) {
        const labels = sectionOrder
            .filter((section) => gapItems.has(section.key))
            .map((section) => section.short);

        lines.push(
            `Sensor coverage gaps on ${labels.join(', ')} — figures for those items may be incomplete.`,
        );
    }

    return lines;
}

function DataTable({
    columns,
    rows,
    emptyLabel,
    emptyHint,
}: {
    columns: Array<{ key: string; label: string; className?: string }>;
    rows: Array<Record<string, ReactNode>>;
    emptyLabel?: string;
    emptyHint?: string;
}) {
    if (rows.length === 0) {
        return <EmptyState label={emptyLabel} hint={emptyHint} />;
    }

    return (
        <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full min-w-[520px] text-left text-sm">
                <thead className="bg-surface-2/60 text-xs tracking-wide text-text-dim uppercase">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                className={cn(
                                    'px-3 py-2 font-semibold',
                                    column.className,
                                )}
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr
                            key={index}
                            className="border-t border-border/80 odd:bg-surface even:bg-surface-2/20"
                        >
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={cn(
                                        'px-3 py-2 align-top text-text',
                                        column.className,
                                    )}
                                >
                                    {row[column.key] ?? '—'}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function buildSummary(data: WeeklyReportData) {
    const ppeDays = data.i_daily_safety_observations?.per_day ?? [];
    const ppeTotal = sumBy(ppeDays, (row) => row.total);
    const ppeTypes = mergeCounts(ppeDays.map((row) => row.by_type));

    const incidents = data.ii_hse_incidents ?? [];
    const incidentSeverities = mergeCounts(
        incidents.map((raw) => {
            const severity = str(asRecord(raw).severity);

            return severity ? { [severity]: 1 } : {};
        }),
    );

    const lsrEntries = data.iii_lsr_violations?.entries ?? [];
    const lsrCats = (data.iii_lsr_violations?.summary_by_category ?? [])
        .map((row) => `${labelize(row.category)} (${row.count})`)
        .join(', ');

    const weatherDays = data.iv_weather?.per_day ?? [];
    const weatherTemps = weatherDays.map((day) =>
        num(asRecord(asRecord(day).temp).avg),
    );
    const weatherHumidity = weatherDays.map((day) =>
        num(asRecord(asRecord(day).humidity).avg),
    );

    const weatherHasData = weatherTemps.some((v) => v !== null);

    const manpowerDays = data.v_manpower?.per_day ?? [];
    const peakManpower = maxOf(
        manpowerDays.map((day) => num(asRecord(day).peak)),
    );
    const avgManpower = avgOf(
        manpowerDays.map((day) => num(asRecord(day).average)),
    );
    const manpowerActive =
        (peakManpower ?? 0) > 0 ||
        manpowerDays.some((day) => {
            const row = asRecord(day);

            return (
                (num(row.entries) ?? 0) > 0 || (num(row.samples) ?? 0) > 0
            );
        });

    const units = data.vi_units_monitored?.count ?? 0;

    const vehicles = data.vii_vehicle_violations ?? [];
    const vehicleTypes = mergeCounts(
        vehicles.map((raw) => {
            const type = str(asRecord(raw).violation_type);

            return type ? { [type]: 1 } : {};
        }),
    );

    const gasDays = data.ix_gas?.per_day ?? [];
    const gasAlarms = data.ix_gas?.alarm_events ?? [];
    const gasAvg = (channel: string): number | null =>
        avgOf(gasDays.map((day) => num(asRecord(asRecord(day)[channel]).avg)));
    const gasDetailParts = [
        `LEL ${formatNumber(gasAvg('lel'))}%`,
        `H₂S ${formatNumber(gasAvg('h2s'))}`,
        `O₂ ${formatNumber(gasAvg('o2'))}%`,
        `CO ${formatNumber(gasAvg('co'))}`,
        `CO₂ ${formatNumber(gasAvg('co2'), 0)}`,
    ];

    return [
        {
            key: 'i_daily_safety_observations',
            label: 'i. Safety observations',
            value: String(ppeTotal),
            detail:
                [byTypeSummary(ppeTypes, 2)].filter(Boolean).join(' · ') ||
                'No confirmed events',
            tone: ppeTotal > 0 ? ('warn' as const) : ('ok' as const),
        },
        {
            key: 'ii_hse_incidents',
            label: 'ii. HSE incidents',
            value: String(incidents.length),
            detail:
                incidents.length === 0
                    ? 'None logged'
                    : byTypeSummary(incidentSeverities, 3),
            tone: incidents.length > 0 ? ('crit' as const) : ('ok' as const),
        },
        {
            key: 'iii_lsr_violations',
            label: 'iii. LSR violations',
            value: String(lsrEntries.length),
            detail: lsrCats || 'None logged',
            tone: lsrEntries.length > 0 ? ('warn' as const) : ('ok' as const),
        },
        {
            key: 'iv_weather',
            label: 'iv. Weather',
            value: weatherHasData
                ? `${formatNumber(avgOf(weatherTemps))} °C`
                : 'No data',
            detail: weatherHasData
                ? `Avg RH ${formatNumber(avgOf(weatherHumidity), 0)}%`
                : 'Weather feed empty',
            tone: weatherHasData ? ('neutral' as const) : ('warn' as const),
        },
        {
            key: 'v_manpower',
            label: 'v. Manpower',
            value: manpowerActive ? formatNumber(peakManpower, 0) : '0',
            detail: manpowerActive
                ? `Peak · avg ${formatNumber(avgManpower, 0)}/day`
                : 'No headcount this week',
            tone: manpowerActive ? ('accent' as const) : ('warn' as const),
        },
        {
            key: 'vi_units_monitored',
            label: 'vi. Units monitored',
            value: String(units),
            detail: 'Active field poles / units',
            tone: 'neutral' as const,
        },
        {
            key: 'vii_vehicle_violations',
            label: 'vii. Vehicle violations',
            value: String(vehicles.length),
            detail:
                vehicles.length === 0
                    ? 'None logged'
                    : byTypeSummary(vehicleTypes, 2),
            tone: vehicles.length > 0 ? ('warn' as const) : ('ok' as const),
        },
        {
            key: 'ix_gas',
            label: 'viii. Gas monitoring',
            value: String(gasAlarms.length),
            detail:
                gasAlarms.length > 0
                    ? `${pluralize(gasAlarms.length, 'alarm')} · ${gasDetailParts.slice(0, 3).join(' · ')}`
                    : `No alarms · ${gasDetailParts.slice(0, 3).join(' · ')}`,
            tone: gasAlarms.length > 0 ? ('crit' as const) : ('ok' as const),
        },
    ];
}

function SectionBody({
    sectionKey,
    data,
}: {
    sectionKey: keyof WeeklyReportData;
    data: WeeklyReportData;
}) {
    if (sectionKey === 'i_daily_safety_observations') {
        const section = data.i_daily_safety_observations;
        const cameras = section?.by_camera ?? [];
        const perDay = section?.per_day ?? [];
        const total = sumBy(perDay, (row) => row.total);
        const types = mergeCounts(perDay.map((row) => row.by_type));
        const typeEntries = Object.entries(types)
            .filter(([, count]) => count > 0)
            .sort((a, b) => b[1] - a[1]);

        return (
            <div className="space-y-4">
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <DetailField label="Confirmed observations" value={total} />
                    <DetailField
                        label="Cameras reporting"
                        value={cameras.length}
                    />
                </div>
                {typeEntries.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {typeEntries.map(([type, count]) => (
                            <StatusPill
                                key={type}
                                label={`${labelize(type)} · ${count}`}
                                tone="warn"
                                showDot={false}
                            />
                        ))}
                    </div>
                )}
                <DataTable
                    columns={[
                        { key: 'date', label: 'Date' },
                        { key: 'total', label: 'Total', className: 'w-20' },
                        { key: 'types', label: 'By type' },
                    ]}
                    rows={perDay.map((row) => ({
                        date: formatDate(row.date),
                        total: row.total,
                        types: byTypeSummary(row.by_type),
                    }))}
                    emptyLabel="No confirmed PPE observations this week."
                    emptyHint="No PPE detections were confirmed for this period."
                />
                {cameras.length > 0 && (
                    <>
                        <h3 className="text-xs font-semibold tracking-wide text-text-dim uppercase">
                            By camera
                        </h3>
                        <DataTable
                            columns={[
                                { key: 'camera', label: 'Camera' },
                                {
                                    key: 'total',
                                    label: 'Total',
                                    className: 'w-24',
                                },
                            ]}
                            rows={cameras.map((row) => ({
                                camera: cameraRefLabel(row.camera),
                                total: row.total,
                            }))}
                        />
                    </>
                )}
            </div>
        );
    }

    if (sectionKey === 'ii_hse_incidents') {
        const rows = (data.ii_hse_incidents ?? []).map((raw) => {
            const row = asRecord(raw);

            return {
                number: str(row.incident_number),
                when: formatDateTime(str(row.occurred_at)),
                type: labelize(str(row.type)),
                severity: labelize(str(row.severity)),
                status: labelize(str(row.status)),
                immediate: str(row.immediate_action),
                corrective: str(row.corrective_action),
            };
        });

        return (
            <DataTable
                columns={[
                    { key: 'number', label: 'Number' },
                    { key: 'when', label: 'Occurred' },
                    { key: 'type', label: 'Type' },
                    { key: 'severity', label: 'Severity' },
                    { key: 'status', label: 'Status' },
                    { key: 'immediate', label: 'Immediate action' },
                    { key: 'corrective', label: 'Corrective action' },
                ]}
                rows={rows}
                emptyLabel="No HSE incidents logged this week."
                emptyHint="Incidents are created by operators — none were recorded for this period."
            />
        );
    }

    if (sectionKey === 'iii_lsr_violations') {
        const summary = data.iii_lsr_violations?.summary_by_category ?? [];
        const entries = (data.iii_lsr_violations?.entries ?? []).map((raw) => {
            const row = asRecord(raw);

            return {
                category: labelize(str(row.category)),
                when: formatDateTime(str(row.occurred_at)),
                worker: str(row.worker),
                zone: str(row.zone),
                action: str(row.action_taken),
                status: labelize(str(row.status)),
            };
        });

        return (
            <div className="space-y-4">
                {summary.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {summary.map((row) => (
                            <StatusPill
                                key={row.category}
                                label={`${labelize(row.category)} · ${row.count}`}
                                tone="warn"
                            />
                        ))}
                    </div>
                )}
                <DataTable
                    columns={[
                        { key: 'category', label: 'Category' },
                        { key: 'when', label: 'Occurred' },
                        { key: 'worker', label: 'Worker' },
                        { key: 'zone', label: 'Zone' },
                        { key: 'action', label: 'Action taken' },
                        { key: 'status', label: 'Status' },
                    ]}
                    rows={entries}
                    emptyLabel="No LSR violations logged this week."
                    emptyHint="Life-Saving Rule entries are operator-created."
                />
            </div>
        );
    }

    if (sectionKey === 'iv_weather') {
        const days = data.iv_weather?.per_day ?? [];
        const rows = days
            .map((raw) => {
                const row = asRecord(raw);
                const temp = asRecord(row.temp);
                const humidity = asRecord(row.humidity);
                const hasTemp =
                    num(temp.min) !== null ||
                    num(temp.avg) !== null ||
                    num(temp.max) !== null;
                const hasHumidity =
                    num(humidity.min) !== null ||
                    num(humidity.avg) !== null ||
                    num(humidity.max) !== null;

                return {
                    date: formatDate(str(row.date)),
                    temp: hasTemp
                        ? rangeLabel(
                              num(temp.min),
                              num(temp.avg),
                              num(temp.max),
                          )
                        : 'No sample',
                    humidity: hasHumidity
                        ? rangeLabel(
                              num(humidity.min),
                              num(humidity.avg),
                              num(humidity.max),
                          )
                        : 'No sample',
                    hasData: hasTemp || hasHumidity,
                };
            })
            .filter((row) => row.hasData);

        if (rows.length === 0) {
            return (
                <EmptyState
                    label="No weather samples in this period."
                    hint="The weather feed was empty or offline — check Data completeness if an outage was declared."
                />
            );
        }

        const temps = days.map((day) => num(asRecord(asRecord(day).temp).avg));
        const humidities = days.map((day) =>
            num(asRecord(asRecord(day).humidity).avg),
        );

        return (
            <div className="space-y-4">
                <div className="grid gap-2 sm:grid-cols-2">
                    <DetailField
                        label="Week avg temperature"
                        value={`${formatNumber(avgOf(temps))} °C`}
                    />
                    <DetailField
                        label="Week avg humidity"
                        value={`${formatNumber(avgOf(humidities), 0)}%`}
                    />
                </div>
                <DataTable
                    columns={[
                        { key: 'date', label: 'Date' },
                        { key: 'temp', label: 'Temp °C (min · avg · max)' },
                        {
                            key: 'humidity',
                            label: 'Humidity % (min · avg · max)',
                        },
                    ]}
                    rows={rows}
                />
            </div>
        );
    }

    if (sectionKey === 'v_manpower') {
        const days = data.v_manpower?.per_day ?? [];
        const hasMovement = days.some((raw) => {
            const row = asRecord(raw);

            return (
                (num(row.peak) ?? 0) > 0 ||
                (num(row.entries) ?? 0) > 0 ||
                (num(row.exits) ?? 0) > 0 ||
                (num(row.samples) ?? 0) > 0
            );
        });

        if (!hasMovement) {
            return (
                <EmptyState
                    label="No headcount this week."
                    hint="Peak and average stayed at zero. Check coverage notes under Data completeness."
                />
            );
        }

        const rows = days.map((raw) => {
            const row = asRecord(raw);

            return {
                date: formatDate(str(row.date)),
                peak: formatNumber(num(row.peak), 0),
                average: formatNumber(num(row.average), 1),
            };
        });

        return (
            <DataTable
                columns={[
                    { key: 'date', label: 'Date' },
                    { key: 'peak', label: 'Peak' },
                    { key: 'average', label: 'Average' },
                ]}
                rows={rows}
            />
        );
    }

    if (sectionKey === 'vi_units_monitored') {
        const count = data.vi_units_monitored?.count ?? 0;
        const note =
            data.vi_units_monitored?.note ||
            'Active field units with monitoring devices';

        return (
            <div className="space-y-3">
                <div className="grid gap-2 sm:grid-cols-2">
                    <DetailField label="Active units" value={count} />
                    <DetailField
                        label="What this counts"
                        value="Poles / field assets with live monitoring devices — not fleet telematics"
                    />
                </div>
                <p className="text-sm text-text-dim">{note}</p>
            </div>
        );
    }

    if (sectionKey === 'vii_vehicle_violations') {
        const rows = (data.vii_vehicle_violations ?? []).map((raw) => {
            const row = asRecord(raw);

            return {
                when: formatDateTime(str(row.observed_at)),
                vehicle: str(row.vehicle_description),
                type: labelize(str(row.violation_type)),
                description: str(row.description),
                action: str(row.action_taken),
                by: str(row.logged_by),
            };
        });

        return (
            <DataTable
                columns={[
                    { key: 'when', label: 'Observed' },
                    { key: 'vehicle', label: 'Vehicle' },
                    { key: 'type', label: 'Type' },
                    { key: 'description', label: 'Description' },
                    { key: 'action', label: 'Action taken' },
                    { key: 'by', label: 'Logged by' },
                ]}
                rows={rows}
                emptyLabel="No vehicle violations logged this week."
                emptyHint="This item is entered manually by operators."
            />
        );
    }

    if (sectionKey === 'ix_gas') {
        const gasDays = data.ix_gas?.per_day ?? [];
        const alarmRaw = data.ix_gas?.alarm_events ?? [];
        const gasAvg = (channel: string): number | null =>
            avgOf(
                gasDays.map((day) => num(asRecord(asRecord(day)[channel]).avg)),
            );
        const readings = gasDays.map((raw) => {
            const row = asRecord(raw);

            return {
                date: formatDateCompact(str(row.date)),
                lel: formatMinAvgMax(asRecord(row.lel)),
                h2s: formatMinAvgMax(asRecord(row.h2s)),
                o2: formatMinAvgMax(asRecord(row.o2)),
                co: formatMinAvgMax(asRecord(row.co)),
                co2: formatMinAvgMax(asRecord(row.co2), 0),
            };
        });
        const alarms = alarmRaw.map((raw) => {
            const row = asRecord(raw);

            return {
                when: formatDateTime(str(row.triggered_at)),
                device: deviceRefLabel(str(row.device)),
                gas: labelize(str(row.gas)),
                level: labelize(str(row.level)),
                peak: formatNumber(num(row.peak)),
                duration: row.duration_s ? `${row.duration_s}s` : '—',
                ack: str(row.acknowledged_by),
                duringOutage: Boolean(row.during_outage),
            };
        });
        const duringOutage = alarms.filter((a) => a.duringOutage).length;
        const byGas = mergeCounts(
            alarms.map((a) => (a.gas !== '—' ? { [a.gas]: 1 } : {})),
        );

        return (
            <div className="space-y-4">
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <DetailField label="Alarm events" value={alarms.length} />
                    <DetailField
                        label="During declared outage"
                        value={duringOutage}
                    />
                </div>
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <DetailField
                        label="Week avg LEL"
                        value={`${formatNumber(gasAvg('lel'))}%`}
                    />
                    <DetailField
                        label="Week avg H₂S"
                        value={`${formatNumber(gasAvg('h2s'))} ppm`}
                    />
                    <DetailField
                        label="Week avg O₂"
                        value={`${formatNumber(gasAvg('o2'))}%`}
                    />
                    <DetailField
                        label="Week avg CO"
                        value={`${formatNumber(gasAvg('co'))} ppm`}
                    />
                    <DetailField
                        label="Week avg CO₂"
                        value={`${formatNumber(gasAvg('co2'), 0)} ppm`}
                    />
                </div>
                {Object.keys(byGas).length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {Object.entries(byGas)
                            .sort((a, b) => b[1] - a[1])
                            .map(([gas, count]) => (
                                <StatusPill
                                    key={gas}
                                    label={`${gas} · ${count}`}
                                    tone="crit"
                                    showDot={false}
                                />
                            ))}
                    </div>
                )}
                <h3 className="text-xs font-semibold tracking-wide text-text-dim uppercase">
                    Daily readings (min · avg · max)
                </h3>
                <DataTable
                    columns={[
                        {
                            key: 'date',
                            label: 'Day',
                            className: 'w-16 whitespace-nowrap px-2',
                        },
                        { key: 'lel', label: 'LEL %', className: 'px-2' },
                        { key: 'h2s', label: 'H₂S ppm', className: 'px-2' },
                        { key: 'o2', label: 'O₂ %', className: 'px-2' },
                        { key: 'co', label: 'CO ppm', className: 'px-2' },
                        { key: 'co2', label: 'CO₂ ppm', className: 'px-2' },
                    ]}
                    rows={readings}
                    emptyLabel="No daily gas readings in this period."
                />
                <div>
                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-text-dim uppercase">
                        Alarm events ({alarms.length})
                    </h3>
                    <DataTable
                        columns={[
                            { key: 'when', label: 'Triggered' },
                            { key: 'device', label: 'Device' },
                            { key: 'gas', label: 'Gas' },
                            { key: 'level', label: 'Level' },
                            { key: 'peak', label: 'Peak' },
                            { key: 'duration', label: 'Duration' },
                            { key: 'ack', label: 'Acknowledged by' },
                        ]}
                        rows={alarms}
                        emptyLabel="No gas alarm events this week."
                        emptyHint="Channels stayed within thresholds for the period."
                    />
                </div>
            </div>
        );
    }

    return <EmptyState label="No detail available for this section." />;
}

export default function ReportShow({ report, badges, canPublish }: Props) {
    const notes = report.data.completeness?.notes ?? [];
    const gapItems = coverageGapItems(notes);
    const executiveLines = buildExecutiveLines(report.data);
    const summary = buildSummary(report.data);
    const periodLabel = `${formatDate(report.period_start)} → ${formatDate(report.period_end)}`;

    return (
        <>
            <Head title={report.report_number} />
            <div className="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-5">
                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div
                        className={cn(
                            'h-1.5 w-full',
                            report.status === 'published'
                                ? 'bg-[color:var(--ok)]'
                                : report.status === 'generated'
                                  ? 'bg-[color:var(--accent)]'
                                  : 'bg-[color:var(--warn)]',
                        )}
                        aria-hidden
                    />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="min-w-0 space-y-2">
                            <span className="inline-flex items-center rounded-pill bg-surface-3 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                Weekly report
                            </span>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                {report.report_number}
                            </h1>
                            <p className="text-sm text-text-dim">
                                {periodLabel}
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                <StatusPill
                                    label={report.status_label}
                                    tone={statusTone(report.status)}
                                />
                                {report.generated_by_name && (
                                    <StatusPill
                                        label={`Generated by ${report.generated_by_name}`}
                                        tone="neutral"
                                        showDot={false}
                                    />
                                )}
                                {report.published_by_name && (
                                    <StatusPill
                                        label={`Published by ${report.published_by_name}`}
                                        tone="ok"
                                        showDot={false}
                                    />
                                )}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={reports.index()}>Back</Link>
                            </Button>
                            {report.has_pdf && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={weeklyReports.download.url(
                                            report.uuid,
                                            { query: { format: 'pdf' } },
                                        )}
                                    >
                                        Download PDF
                                    </a>
                                </Button>
                            )}
                            {report.has_csv && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={weeklyReports.download.url(
                                            report.uuid,
                                            { query: { format: 'csv' } },
                                        )}
                                    >
                                        Download CSV
                                    </a>
                                </Button>
                            )}
                            {canPublish && report.status === 'generated' && (
                                <Form
                                    method="post"
                                    action={weeklyReports.publish.url(
                                        report.uuid,
                                    )}
                                >
                                    <Button type="submit">Publish</Button>
                                </Form>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2 border-t border-border bg-surface-2/20 p-4 sm:grid-cols-2 md:px-5 lg:grid-cols-4">
                        <DetailField
                            label="Generated"
                            value={formatDateTime(report.generated_at)}
                        />
                        <DetailField
                            label="Published"
                            value={formatDateTime(report.published_at)}
                        />
                        <DetailField
                            label="Period start"
                            value={formatDate(report.period_start)}
                        />
                        <DetailField
                            label="Period end"
                            value={formatDate(report.period_end)}
                        />
                    </div>
                </header>

                {(report.supersedes_report_number ||
                    report.superseded_by_report_numbers.length > 0) && (
                    <div className="rounded-[var(--radius)] border border-[color:var(--warn)]/40 bg-[color:var(--warn-bg)] px-4 py-3 text-sm text-text">
                        {report.supersedes_report_number && (
                            <p>
                                Supersedes{' '}
                                <strong>
                                    {report.supersedes_report_number}
                                </strong>
                            </p>
                        )}
                        {report.superseded_by_report_numbers.length > 0 && (
                            <p>
                                Superseded by{' '}
                                <strong>
                                    {report.superseded_by_report_numbers.join(
                                        ', ',
                                    )}
                                </strong>
                            </p>
                        )}
                    </div>
                )}

                <Panel
                    title="At a glance"
                    subtitle="What a reviewer needs to know first"
                >
                    <ul className="list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-text">
                        {executiveLines.map((line) => (
                            <li key={line}>{line}</li>
                        ))}
                    </ul>
                </Panel>

                <Panel
                    title="Weekly summary"
                    subtitle="One tile per report item — click to jump"
                >
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {summary.map((item) => {
                            const hasGap = gapItems.has(item.key);

                            return (
                                <a
                                    key={item.key}
                                    href={`#section-${item.key}`}
                                    className="min-w-0 rounded-[var(--radius)] ring-[color:var(--accent)] outline-none focus-visible:ring-2"
                                >
                                    <FactTile
                                        label={item.label}
                                        tone={
                                            hasGap && item.tone === 'ok'
                                                ? 'warn'
                                                : item.tone
                                        }
                                        value={item.value}
                                    />
                                    <p className="mt-1 px-1 text-[11px] leading-snug text-text-dim">
                                        {hasGap
                                            ? `${item.detail} · coverage gap`
                                            : item.detail}
                                    </p>
                                </a>
                            );
                        })}
                    </div>
                </Panel>

                <div className="space-y-4">
                    <div className="sticky top-0 z-10 -mx-1 space-y-2 rounded-[var(--radius)] border border-border bg-surface/95 p-3 backdrop-blur-sm md:mx-0">
                        <div>
                            <h2 className="font-display text-lg font-semibold text-text">
                                Detailed report
                            </h2>
                            <p className="text-sm text-text-dim">
                                Jump to a section. Badges show how each item was
                                produced.
                            </p>
                        </div>
                        <nav
                            aria-label="Report sections"
                            className="flex flex-wrap gap-1.5"
                        >
                            {sectionOrder.map(({ key, short }) => (
                                <a
                                    key={key}
                                    href={`#section-${key}`}
                                    className={cn(
                                        'rounded-pill border px-2.5 py-1 text-[11px] font-medium hover:text-text',
                                        gapItems.has(key)
                                            ? 'border-[color:var(--warn)]/50 bg-[color:var(--warn-bg)]/50 text-text'
                                            : 'border-border bg-surface-2/40 text-text-dim hover:border-[color:var(--accent)]/50',
                                    )}
                                >
                                    {short}
                                    {gapItems.has(key) ? ' · gap' : ''}
                                </a>
                            ))}
                        </nav>
                    </div>

                    {sectionOrder.map(({ key, title, blurb }) => {
                        const coverageMessage = sectionCoverageMessage(
                            notes.filter((note) => note.item === key),
                        );

                        return (
                            <div key={key} id={`section-${key}`}>
                                <Panel
                                    title={title}
                                    action={
                                        badges[key] ? (
                                            <span className="rounded border border-border px-2 py-0.5 text-[11px] text-text-dim">
                                                {badges[key]}
                                            </span>
                                        ) : undefined
                                    }
                                >
                                    <SectionIntro text={blurb} />
                                    {coverageMessage ? (
                                        <div className="mb-3 rounded-md border border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)] px-3 py-2 text-sm text-text">
                                            {coverageMessage}
                                        </div>
                                    ) : null}
                                    <SectionBody
                                        sectionKey={key}
                                        data={report.data}
                                    />
                                </Panel>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [
        { title: 'Reports', href: reports.index() },
        { title: 'Detail', href: '#' },
    ],
};
