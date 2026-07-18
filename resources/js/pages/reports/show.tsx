import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { WeeklyReport } from '@/types/report';

type Props = {
    report: WeeklyReport;
    badges: Record<string, string>;
    canPublish: boolean;
};

const sectionOrder: Array<{ key: keyof WeeklyReport['data']; title: string }> =
    [
        {
            key: 'i_daily_safety_observations',
            title: 'i. Daily Safety Observations',
        },
        { key: 'ii_hse_incidents', title: 'ii. HSE Accidents & Incidents' },
        {
            key: 'iii_lsr_violations',
            title: 'iii. LSR Violations & Actions Taken',
        },
        { key: 'iv_weather', title: 'iv. Weather Conditions' },
        { key: 'v_manpower', title: 'v. Site Manpower' },
        {
            key: 'vi_units_monitored',
            title: 'vi. Total Vehicles/Units Monitored',
        },
        {
            key: 'vii_vehicle_violations',
            title: 'vii. Vehicle Violations & Actions Taken',
        },
        { key: 'viii_environmental', title: 'viii. Environmental Data' },
        { key: 'ix_gas', title: 'ix. Gas Monitoring' },
        { key: 'x_co2', title: 'x. CO₂ Monitoring' },
    ];

export default function ReportShow({ report, badges, canPublish }: Props) {
    const notes = report.data.completeness?.notes ?? [];

    return (
        <>
            <Head title={report.report_number} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={report.report_number}
                        description={`${report.period_start} → ${report.period_end} · ${report.status_label}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/reports">Back</Link>
                        </Button>
                        {report.has_pdf && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/weekly-reports/${report.id}/download?format=pdf`}
                                >
                                    PDF
                                </a>
                            </Button>
                        )}
                        {report.has_csv && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/weekly-reports/${report.id}/download?format=csv`}
                                >
                                    CSV zip
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

                {(report.supersedes_report_number ||
                    report.superseded_by_report_numbers.length > 0) && (
                    <div className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        {report.supersedes_report_number && (
                            <div>
                                Supersedes{' '}
                                <strong>{report.supersedes_report_number}</strong>
                            </div>
                        )}
                        {report.superseded_by_report_numbers.length > 0 && (
                            <div>
                                Superseded by{' '}
                                {report.superseded_by_report_numbers.join(', ')}
                            </div>
                        )}
                    </div>
                )}

                {sectionOrder.map(({ key, title }) => {
                    const sectionNotes = notes.filter(
                        (note) => note.item === key,
                    );

                    return (
                        <section
                            key={key}
                            className="rounded-lg border p-4"
                        >
                            <div className="mb-3 flex flex-wrap items-center gap-2">
                                <h2 className="text-lg font-semibold">
                                    {title}
                                </h2>
                                <span className="rounded border px-2 py-0.5 text-xs text-muted-foreground">
                                    {badges[key]}
                                </span>
                            </div>
                            {sectionNotes.map((note) => (
                                <div
                                    key={note.message}
                                    className="mb-2 rounded border border-amber-500/40 bg-amber-500/10 p-2 text-sm"
                                >
                                    {note.message}
                                </div>
                            ))}
                            <pre className="overflow-x-auto rounded bg-muted/40 p-3 text-xs">
                                {JSON.stringify(report.data[key], null, 2)}
                            </pre>
                        </section>
                    );
                })}
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
