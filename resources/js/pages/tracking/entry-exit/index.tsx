import { Form, Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type LogRow = {
    id: number;
    worker_id: number;
    worker_name: string | null;
    direction: string;
    source: string;
    occurred_at: string;
    correction_note: string | null;
    gate_zone: string | null;
};

type Props = {
    logs: {
        data: LogRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { direction: string; source: string; worker_id: string };
    workers: Array<{ id: number; name: string }>;
    canCorrect: boolean;
};

export default function EntryExitIndex({
    logs,
    filters,
    workers,
    canCorrect,
}: Props) {
    return (
        <>
            <Head title="Entry / exit" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Entry / exit"
                        description="Gate and correction history"
                    />
                    <Button asChild variant="secondary" size="sm">
                        <a href="/tracking/entry-exit/export">CSV export</a>
                    </Button>
                </div>

                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/tracking/entry-exit',
                            {
                                direction: String(form.get('direction') ?? ''),
                                source: String(form.get('source') ?? ''),
                                worker_id: String(form.get('worker_id') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <select
                        name="direction"
                        defaultValue={filters.direction}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All directions</option>
                        <option value="in">In</option>
                        <option value="out">Out</option>
                    </select>
                    <select
                        name="source"
                        defaultValue={filters.source}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All sources</option>
                        <option value="gate_reader">Gate</option>
                        <option value="manual_correction">Manual</option>
                        <option value="auto_sweep">Auto sweep</option>
                    </select>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                {canCorrect && (
                    <Form
                        action="/tracking/entry-exit/corrections"
                        method="post"
                        className="grid gap-2 rounded-lg border border-border p-4 md:grid-cols-4"
                    >
                        {({ processing }) => (
                            <>
                                <select
                                    name="worker_id"
                                    required
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">Worker</option>
                                    {workers.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    name="direction"
                                    required
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="in">In</option>
                                    <option value="out">Out</option>
                                </select>
                                <input
                                    type="datetime-local"
                                    name="occurred_at"
                                    required
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                />
                                <input
                                    name="note"
                                    required
                                    minLength={10}
                                    placeholder="Correction note (≥10)"
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-3"
                                />
                                <Button type="submit" disabled={processing}>
                                    Add correction
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">When</th>
                                <th className="px-3 py-2">Worker</th>
                                <th className="px-3 py-2">Dir</th>
                                <th className="px-3 py-2">Source</th>
                                <th className="px-3 py-2">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {log.occurred_at}
                                    </td>
                                    <td className="px-3 py-2">
                                        {log.worker_name}
                                    </td>
                                    <td className="px-3 py-2">
                                        {log.direction}
                                    </td>
                                    <td className="px-3 py-2">{log.source}</td>
                                    <td className="px-3 py-2">
                                        {log.correction_note ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Link href="/tracking" className="text-sm underline">
                    Back to tracking
                </Link>
            </div>
        </>
    );
}
