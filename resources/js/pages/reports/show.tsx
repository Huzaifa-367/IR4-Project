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
    formatDate,
    formatDateCompact,
    formatDateTime,
    formatMinAvgMax,
    formatNumber,
    labelize,
    maxOf,
    mergeCounts,
    sumBy,
} from '@/lib/report-format';
import { cn } from '@/lib/utils';
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
};

const sectionOrder: SectionDef[] = [
    {
        key: 'i_daily_safety_observations',
        title: 'i. Daily Safety Observations',
        short: 'Safety observations',
    },
    {
        key: 'ii_hse_incidents',
        title: 'ii. HSE Accidents & Incidents',
        short: 'HSE incidents',
    },
    {
        key: 'iii_lsr_violations',
        title: 'iii. LSR Violations & Actions Taken',
        short: 'LSR violations',
    },
    {
        key: 'iv_weather',
        title: 'iv. Weather Conditions',
        short: 'Weather',
    },
    {
        key: 'v_manpower',
        title: 'v. Site Manpower',
        short: 'Manpower',
    },
    {
        key: 'vi_units_monitored',
        title: 'vi. Total Vehicles/Units Monitored',
        short: 'Units monitored',
    },
    {
        key: 'vii_vehicle_violations',
        title: 'vii. Vehicle Violations & Actions Taken',
        short: 'Vehicle violations',
    },
    {
        key: 'viii_environmental',
        title: 'viii. Environmental Data',
        short: 'Environmental',
    },
    {
        key: 'ix_gas',
        title: 'ix. Gas Monitoring (LEL / H₂S / O₂ / CO / CO₂)',
        short: 'Gas',
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

function EmptyState({ label = 'No records in this period.' }: { label?: string }) {
    return (
        <p className="rounded-md border border-dashed border-border bg-surface-2/30 px-3 py-6 text-center text-sm text-text-dim">
            {label}
        </p>
    );
}

function DataTable({
    columns,
    rows,
}: {
    columns: Array<{ key: string; label: string; className?: string }>;
    rows: Array<Record<string, ReactNode>>;
}) {
    if (rows.length === 0) {
        return <EmptyState />;
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
    const fpExcluded =
        data.i_daily_safety_observations?.false_positives_excluded ?? 0;

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
    const weatherWind = weatherDays.map((day) =>
        num(asRecord(asRecord(day).wind).avg),
    );

    const manpowerDays = data.v_manpower?.per_day ?? [];
    const peakManpower = maxOf(
        manpowerDays.map((day) => num(asRecord(day).peak)),
    );
    const avgManpower = avgOf(
        manpowerDays.map((day) => num(asRecord(day).average)),
    );

    const units = data.vi_units_monitored?.count ?? 0;

    const vehicles = data.vii_vehicle_violations ?? [];
    const vehicleTypes = mergeCounts(
        vehicles.map((raw) => {
            const type = str(asRecord(raw).violation_type);

            return type ? { [type]: 1 } : {};
        }),
    );

    const envDays = data.viii_environmental?.per_day ?? [];
    const envSampled = envDays.filter((day) => {
        const air = asRecord(asRecord(day).air_quality);

        return Object.keys(air).length > 0;
    }).length;
    const envParams = Array.from(
        new Set(
            envDays.flatMap((day) =>
                Object.keys(asRecord(asRecord(day).air_quality)),
            ),
        ),
    );

    const gasDays = data.ix_gas?.per_day ?? [];
    const gasAlarms = data.ix_gas?.alarm_events ?? [];
    const gasAvg = (channel: string): number | null =>
        avgOf(
            gasDays.map((day) => num(asRecord(asRecord(day)[channel]).avg)),
        );
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
                [
                    byTypeSummary(ppeTypes, 2),
                    fpExcluded > 0 ? `${fpExcluded} FP excluded` : null,
                ]
                    .filter(Boolean)
                    .join(' · ') || 'No confirmed events',
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
            tone:
                incidents.length > 0 ? ('crit' as const) : ('ok' as const),
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
            value: `${formatNumber(avgOf(weatherTemps))} °C`,
            detail: `RH ${formatNumber(avgOf(weatherHumidity), 0)}% · Wind ${formatNumber(avgOf(weatherWind))} m/s`,
            tone: 'neutral' as const,
        },
        {
            key: 'v_manpower',
            label: 'v. Manpower',
            value: formatNumber(peakManpower, 0),
            detail: `Peak · avg ${formatNumber(avgManpower, 0)}/day`,
            tone: 'accent' as const,
        },
        {
            key: 'vi_units_monitored',
            label: 'vi. Units monitored',
            value: String(units),
            detail: 'Active field units',
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
            tone:
                vehicles.length > 0 ? ('warn' as const) : ('ok' as const),
        },
        {
            key: 'viii_environmental',
            label: 'viii. Environmental',
            value: String(envSampled),
            detail:
                envParams.length === 0
                    ? 'No air-quality samples'
                    : `${envSampled}/${envDays.length} days · ${envParams.slice(0, 3).join(', ')}`,
            tone: 'neutral' as const,
        },
        {
            key: 'ix_gas',
            label: 'ix. Gas monitoring',
            value: String(gasAlarms.length),
            detail:
                gasAlarms.length > 0
                    ? `${gasAlarms.length} alarm(s) · ${gasDetailParts.slice(0, 3).join(' · ')}`
                    : gasDetailParts.join(' · '),
            tone:
                gasAlarms.length > 0 ? ('crit' as const) : ('ok' as const),
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

        return (
            <div className="space-y-4">
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <DetailField
                        label="Confirmed observations"
                        value={sumBy(section?.per_day ?? [], (row) => row.total)}
                    />
                    <DetailField
                        label="False positives excluded"
                        value={section?.false_positives_excluded ?? 0}
                    />
                    <DetailField
                        label="Cameras reporting"
                        value={cameras.length}
                    />
                </div>
                <DataTable
                    columns={[
                        { key: 'date', label: 'Date' },
                        { key: 'total', label: 'Total', className: 'w-20' },
                        { key: 'types', label: 'By type' },
                    ]}
                    rows={(section?.per_day ?? []).map((row) => ({
                        date: formatDate(row.date),
                        total: row.total,
                        types: byTypeSummary(row.by_type),
                    }))}
                />
                {cameras.length > 0 && (
                    <DataTable
                        columns={[
                            { key: 'camera', label: 'Camera' },
                            { key: 'total', label: 'Total', className: 'w-24' },
                        ]}
                        rows={cameras.map((row) => ({
                            camera: row.camera,
                            total: row.total,
                        }))}
                    />
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
                />
            </div>
        );
    }

    if (sectionKey === 'iv_weather') {
        const rows = (data.iv_weather?.per_day ?? []).map((raw) => {
            const row = asRecord(raw);
            const temp = asRecord(row.temp);
            const humidity = asRecord(row.humidity);
            const wind = asRecord(row.wind);

            return {
                date: formatDate(str(row.date)),
                temp: rangeLabel(num(temp.min), num(temp.avg), num(temp.max), '°C'),
                humidity: rangeLabel(
                    num(humidity.min),
                    num(humidity.avg),
                    num(humidity.max),
                    '%',
                ),
                wind: rangeLabel(num(wind.min), num(wind.avg), num(wind.max), 'm/s'),
            };
        });

        return (
            <DataTable
                columns={[
                    { key: 'date', label: 'Date' },
                    { key: 'temp', label: 'Temp (min / avg / max)' },
                    { key: 'humidity', label: 'Humidity (min / avg / max)' },
                    { key: 'wind', label: 'Wind (min / avg / max)' },
                ]}
                rows={rows}
            />
        );
    }

    if (sectionKey === 'v_manpower') {
        const rows = (data.v_manpower?.per_day ?? []).map((raw) => {
            const row = asRecord(raw);

            return {
                date: formatDate(str(row.date)),
                peak: formatNumber(num(row.peak), 0),
                average: formatNumber(num(row.average), 1),
                entries: formatNumber(num(row.entries), 0),
                exits: formatNumber(num(row.exits), 0),
            };
        });

        return (
            <DataTable
                columns={[
                    { key: 'date', label: 'Date' },
                    { key: 'peak', label: 'Peak' },
                    { key: 'average', label: 'Average' },
                    { key: 'entries', label: 'Entries' },
                    { key: 'exits', label: 'Exits' },
                ]}
                rows={rows}
            />
        );
    }

    if (sectionKey === 'vi_units_monitored') {
        return (
            <div className="grid gap-2 sm:grid-cols-2">
                <DetailField
                    label="Active units"
                    value={data.vi_units_monitored?.count ?? 0}
                />
                <DetailField
                    label="Note"
                    value={data.vi_units_monitored?.note || '—'}
                />
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
            />
        );
    }

    if (sectionKey === 'viii_environmental') {
        const rows = (data.viii_environmental?.per_day ?? []).map((raw) => {
            const row = asRecord(raw);
            const air = asRecord(row.air_quality);
            const airParts = Object.entries(air)
                .filter(([, value]) => value !== null && value !== undefined)
                .map(([key, value]) => `${labelize(key)}: ${formatNumber(num(value) ?? Number(value))}`);

            return {
                date: formatDate(str(row.date)),
                air: airParts.length > 0 ? airParts.join(' · ') : 'No air-quality samples',
            };
        });

        return (
            <DataTable
                columns={[
                    { key: 'date', label: 'Date' },
                    { key: 'air', label: 'Air quality' },
                ]}
                rows={rows}
            />
        );
    }

    if (sectionKey === 'ix_gas') {
        const readings = (data.ix_gas?.per_day ?? []).map((raw) => {
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
        const alarms = (data.ix_gas?.alarm_events ?? []).map((raw) => {
            const row = asRecord(raw);

            return {
                when: formatDateTime(str(row.triggered_at)),
                device: str(row.device),
                gas: labelize(str(row.gas)),
                level: labelize(str(row.level)),
                peak: formatNumber(num(row.peak)),
                duration: row.duration_s ? `${row.duration_s}s` : '—',
                ack: str(row.acknowledged_by),
            };
        });

        return (
            <div className="space-y-4">
                <p className="text-xs text-text-dim">
                    Cells show min / avg / max for each channel.
                </p>
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
                />
                <div>
                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-text-dim uppercase">
                        Alarm events
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
                    />
                </div>
            </div>
        );
    }

    return <EmptyState label="No detail available for this section." />;
}

export default function ReportShow({ report, badges, canPublish }: Props) {
    const notes = report.data.completeness?.notes ?? [];
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
                            <span className="inline-flex items-center rounded-pill bg-surface-3 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase text-text-dim">
                                Weekly report
                            </span>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                {report.report_number}
                            </h1>
                            <p className="text-sm text-text-dim">{periodLabel}</p>
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
                                <Link href="/reports">Back</Link>
                            </Button>
                            {report.has_pdf && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`/weekly-reports/${report.id}/download?format=pdf`}
                                    >
                                        Download PDF
                                    </a>
                                </Button>
                            )}
                            {report.has_csv && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`/weekly-reports/${report.id}/download?format=csv`}
                                    >
                                        Download CSV
                                    </a>
                                </Button>
                            )}
                            {canPublish && report.status === 'generated' && (
                                <Form
                                    method="post"
                                    action={`/weekly-reports/${report.id}/publish`}
                                >
                                    <Button type="submit">Publish</Button>
                                </Form>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2 border-t border-border bg-surface-2/20 p-4 sm:grid-cols-2 lg:grid-cols-4 md:px-5">
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
                                <strong>{report.supersedes_report_number}</strong>
                            </p>
                        )}
                        {report.superseded_by_report_numbers.length > 0 && (
                            <p>
                                Superseded by{' '}
                                <strong>
                                    {report.superseded_by_report_numbers.join(', ')}
                                </strong>
                            </p>
                        )}
                    </div>
                )}

                {notes.length > 0 && (
                    <Panel
                        title="Data completeness"
                        subtitle="Sensor outages declared for this period"
                    >
                        <ul className="space-y-2">
                            {notes.map((note) => (
                                <li
                                    key={`${note.item}-${note.message}`}
                                    className="rounded-md border border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)] px-3 py-2 text-sm"
                                >
                                    <span className="font-medium">
                                        {sectionOrder.find((s) => s.key === note.item)
                                            ?.short ?? labelize(note.item)}
                                        :
                                    </span>{' '}
                                    {note.message}
                                </li>
                            ))}
                        </ul>
                    </Panel>
                )}

                <Panel
                    title="Weekly summary"
                    subtitle="One tile per report item — headline figure and concise detail"
                >
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {summary.map((item) => (
                            <div key={item.key} className="min-w-0">
                                <FactTile
                                    label={item.label}
                                    tone={item.tone}
                                    value={item.value}
                                />
                                <p className="mt-1 px-1 text-[11px] leading-snug text-text-dim">
                                    {item.detail}
                                </p>
                            </div>
                        ))}
                    </div>
                </Panel>

                <div className="space-y-4">
                    <div>
                        <h2 className="font-display text-lg font-semibold text-text">
                            Detailed report
                        </h2>
                        <p className="text-sm text-text-dim">
                            Full day-by-day and event-level figures for the period.
                        </p>
                    </div>

                    {sectionOrder.map(({ key, title }) => {
                        const sectionNotes = notes.filter(
                            (note) => note.item === key,
                        );

                        return (
                            <Panel
                                key={key}
                                title={title}
                                action={
                                    badges[key] ? (
                                        <span className="rounded border border-border px-2 py-0.5 text-[11px] text-text-dim">
                                            {badges[key]}
                                        </span>
                                    ) : undefined
                                }
                            >
                                {sectionNotes.map((note) => (
                                    <div
                                        key={note.message}
                                        className="mb-3 rounded-md border border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)] px-3 py-2 text-sm"
                                    >
                                        {note.message}
                                    </div>
                                ))}
                                <SectionBody sectionKey={key} data={report.data} />
                            </Panel>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [
        { title: 'Reports', href: '/reports' },
        { title: 'Detail', href: '#' },
    ],
};
