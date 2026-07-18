import { Form, Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { WeeklyReport } from '@/types/report';

type Props = {
    reports: {
        data: WeeklyReport[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { status: string; search: string };
    statuses: Array<{ value: string; label: string }>;
    canGenerate: boolean;
    canPublish: boolean;
    canManageSettings: boolean;
    canLogVehicles: boolean;
};

export default function ReportsIndex({
    reports,
    filters,
    statuses,
    canGenerate,
    canManageSettings,
    canLogVehicles,
}: Props) {
    return (
        <>
            <Head title="Weekly reports" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Weekly reports"
                        description="Frozen Section 6.5 compliance packages"
                    />
                    <div className="flex flex-wrap gap-2">
                        {canLogVehicles && (
                            <Button variant="outline" asChild>
                                <Link href="/reports/vehicle-violations">
                                    Vehicle violations
                                </Link>
                            </Button>
                        )}
                        {canManageSettings && (
                            <Button variant="outline" asChild>
                                <Link href="/reports/settings">Settings</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {canGenerate && (
                    <Form
                        method="post"
                        action="/weekly-reports/generate"
                        className="grid gap-3 rounded-lg border p-4 md:grid-cols-4"
                    >
                        <div>
                            <Label htmlFor="period_start">Period start</Label>
                            <Input
                                id="period_start"
                                name="period_start"
                                type="date"
                                required
                            />
                        </div>
                        <div>
                            <Label htmlFor="period_end">Period end</Label>
                            <Input
                                id="period_end"
                                name="period_end"
                                type="date"
                                required
                            />
                        </div>
                        <div className="flex items-end md:col-span-2">
                            <Button type="submit">Generate now</Button>
                        </div>
                    </Form>
                )}

                <div className="flex flex-wrap gap-2">
                    <Input
                        placeholder="Search report number"
                        defaultValue={filters.search}
                        className="max-w-xs"
                        onBlur={(event) =>
                            router.get(
                                '/reports',
                                {
                                    ...filters,
                                    search: event.target.value,
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                    <select
                        className="rounded-md border px-3 py-2 text-sm"
                        defaultValue={filters.status}
                        onChange={(event) =>
                            router.get(
                                '/reports',
                                {
                                    ...filters,
                                    status: event.target.value,
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Report</th>
                                <th className="p-3">Period</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Supersedes</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {reports.data.map((report) => (
                                <tr key={report.id} className="border-t">
                                    <td className="p-3 font-medium">
                                        {report.report_number}
                                    </td>
                                    <td className="p-3">
                                        {report.period_start} →{' '}
                                        {report.period_end}
                                    </td>
                                    <td className="p-3">
                                        {report.status_label}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {report.supersedes_report_number ?? '—'}
                                    </td>
                                    <td className="p-3 text-right">
                                        <Link
                                            href={`/reports/${report.id}`}
                                            className="text-primary underline"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {reports.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No reports yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
