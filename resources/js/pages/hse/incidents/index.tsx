import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/ir4/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { HseIncident, HseOption } from '@/types/hse';

type Props = {
    incidents: {
        data: HseIncident[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    filters: {
        search: string;
        status: string;
        source: string;
        incident_type: string;
        severity: string;
        sort: string;
        direction: string;
    };
    statusOptions: HseOption[];
    canLog: boolean;
    canClassify: boolean;
};

export default function IncidentsIndex({
    incidents,
    filters,
    statusOptions,
    canLog,
}: Props) {
    function applyFilters(patch: Partial<Props['filters']>): void {
        router.get(
            '/incidents',
            {
                search: patch.search ?? filters.search,
                status: patch.status ?? filters.status,
                source: patch.source ?? filters.source,
                incident_type: patch.incident_type ?? filters.incident_type,
                severity: patch.severity ?? filters.severity,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="HSE Incidents" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="HSE Incidents"
                        description="User-authored safety records. Alerts may prefill — nothing is saved until submit."
                    />
                    {canLog && (
                        <Button asChild>
                            <Link href="/incidents/create">Log incident</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={filters.status === '' ? 'default' : 'outline'}
                        onClick={() => applyFilters({ status: '' })}
                    >
                        All
                    </Button>
                    {statusOptions.map((option) => (
                        <Button
                            key={option.value}
                            type="button"
                            size="sm"
                            variant={
                                filters.status === option.value
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() =>
                                applyFilters({ status: option.value })
                            }
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        applyFilters({
                            search: String(form.get('search') ?? ''),
                        });
                    }}
                >
                    <div className="grid gap-1">
                        <label className="text-xs text-muted-foreground">
                            Search
                        </label>
                        <Input
                            name="search"
                            defaultValue={filters.search}
                            className="w-56"
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-3 py-2">Number</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Source</th>
                                <th className="px-3 py-2">Occurred</th>
                                <th className="px-3 py-2">Zone</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {incidents.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2 font-mono">
                                        {row.incident_number}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.status_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.source_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.occurred_at}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.zone_name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button asChild size="sm" variant="ghost">
                                            <Link href={`/incidents/${row.id}`}>
                                                Open
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {incidents.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No incidents yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <Pagination
                        meta={incidents.meta}
                        pageUrl="/incidents"
                        params={filters}
                    />
                </div>
            </div>
        </>
    );
}
