import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    summary: {
        open: number;
        by_category: Array<{
            category: string;
            label: string;
            open: number;
            closed: number;
            total: number;
        }>;
    };
    filters: { from: string; to: string };
};

export default function LsrSummary({ summary, filters }: Props) {
    return (
        <>
            <Head title="LSR Summary" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="LSR Summary"
                        description={`${summary.open} open across the selected range.`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/lsr-violations">Back</Link>
                    </Button>
                </div>

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get('/lsr-violations/summary', {
                            from: String(form.get('from') ?? ''),
                            to: String(form.get('to') ?? ''),
                        });
                    }}
                >
                    <div className="grid gap-1">
                        <label className="text-xs text-muted-foreground">
                            From
                        </label>
                        <Input
                            type="date"
                            name="from"
                            defaultValue={filters.from}
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs text-muted-foreground">
                            To
                        </label>
                        <Input
                            type="date"
                            name="to"
                            defaultValue={filters.to}
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Apply
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-3 py-2">Category</th>
                                <th className="px-3 py-2">Open</th>
                                <th className="px-3 py-2">Closed</th>
                                <th className="px-3 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {summary.by_category.map((row) => (
                                <tr
                                    key={row.category}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">{row.label}</td>
                                    <td className="px-3 py-2">{row.open}</td>
                                    <td className="px-3 py-2">{row.closed}</td>
                                    <td className="px-3 py-2">{row.total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
