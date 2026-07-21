import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { PaginatedMeta } from '@/types/hardware';
import type { WeeklyReport } from '@/types/report';

type Props = {
    reports: { data: WeeklyReport[]; meta: PaginatedMeta };
    filters: { status: string; search: string };
    statuses: Array<{ value: string; label: string }>;
    canGenerate: boolean;
    canPublish: boolean;
    canManageSettings: boolean;
    canLogVehicles: boolean;
};

const ALL = 'all';

function statusTone(status: string): 'ok' | 'warn' | 'accent' | 'neutral' {
    if (status === 'published') {
        return 'ok';
    }

    if (status === 'generated') {
        return 'accent';
    }

    if (status === 'superseded') {
        return 'neutral';
    }

    return 'warn';
}

export default function ReportsIndex({
    reports,
    filters,
    statuses,
    canGenerate,
    canManageSettings,
    canLogVehicles,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);
    const [generateOpen, setGenerateOpen] = useState(false);

    const queryParams = {
        search: search || undefined,
        status: status === ALL ? undefined : status,
    };

    function applyFilters(
        patch: Partial<{ search: string; status: string }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextStatus = patch.status ?? status;

        visitFilters('/reports', {
            search: nextSearch || undefined,
            status: nextStatus === ALL ? undefined : nextStatus,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const columns: SettingsColumn<WeeklyReport>[] = [
        {
            key: 'report',
            header: 'Report',
            cell: (row) => (
                <Link
                    href={`/reports/${row.id}`}
                    className="font-medium text-text hover:underline"
                >
                    {row.report_number}
                </Link>
            ),
        },
        {
            key: 'period',
            header: 'Period',
            cell: (row) => `${row.period_start} → ${row.period_end}`,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <StatusPill
                    label={row.status_label}
                    tone={statusTone(row.status)}
                />
            ),
        },
        {
            key: 'supersedes',
            header: 'Supersedes',
            cell: (row) => row.supersedes_report_number ?? '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/reports/${row.id}`}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Weekly reports" />
            <SettingsPageShell
                title="Weekly Reports"
                description="Frozen Section 6.5 compliance packages"
                actions={
                    <>
                        {canLogVehicles && (
                            <Button variant="outline" asChild>
                                <Link href="/reports/vehicle-violations">
                                    Vehicle violations
                                </Link>
                            </Button>
                        )}
                        {canManageSettings && (
                            <Button variant="outline" asChild>
                                <Link href="/settings/reports">Settings</Link>
                            </Button>
                        )}
                        {canGenerate && (
                            <Button
                                type="button"
                                onClick={() => setGenerateOpen(true)}
                            >
                                <Plus data-icon="inline-start" />
                                Generate now
                            </Button>
                        )}
                    </>
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Search report number…"
                            className="w-full sm:w-56"
                            aria-label="Search reports"
                        />
                        <SearchableSelect
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                            placeholder="Status"
                            triggerClassName="w-44"
                            options={[
                                { value: ALL, label: 'All statuses' },
                                ...statuses.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={reports.data}
                    rowKey={(row) => row.id}
                    meta={reports.meta}
                    pageUrl="/reports"
                    queryParams={queryParams}
                    emptyTitle="No reports"
                    emptyDescription="No reports match these filters."
                />
            </SettingsPageShell>

            <Dialog open={generateOpen} onOpenChange={setGenerateOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Generate weekly report</DialogTitle>
                    </DialogHeader>
                    <Form
                        method="post"
                        action="/weekly-reports/generate"
                        className="flex flex-col gap-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setGenerateOpen(false)}
                    >
                        {({ processing }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="period_start">
                                            Period start
                                        </Label>
                                        <Input
                                            id="period_start"
                                            name="period_start"
                                            type="date"
                                            required
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="period_end">
                                            Period end
                                        </Label>
                                        <Input
                                            id="period_end"
                                            name="period_end"
                                            type="date"
                                            required
                                        />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setGenerateOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Generate
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
