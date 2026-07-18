import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/ir4/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { HseOption, LsrPrefill, LsrViolation } from '@/types/hse';

type Props = {
    violations: {
        data: LsrViolation[];
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
        category: string;
        sort: string;
        direction: string;
    };
    categoryOptions: HseOption[];
    statusOptions: HseOption[];
    canLog: boolean;
    canClose: boolean;
    prefill: LsrPrefill | null;
};

export default function LsrIndex({
    violations,
    filters,
    categoryOptions,
    canLog,
    canClose,
}: Props) {
    const [closeId, setCloseId] = useState<number | null>(null);

    function applyFilters(patch: Partial<Props['filters']>): void {
        router.get(
            '/lsr-violations',
            {
                search: patch.search ?? filters.search,
                status: patch.status ?? filters.status,
                category: patch.category ?? filters.category,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="LSR Violations" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Life Saving Rules"
                        description="All LSR rows are user-authored. Closing requires action taken."
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href="/lsr-violations/summary">Summary</Link>
                        </Button>
                        {canLog && (
                            <Button asChild>
                                <Link href="/lsr-violations/create">
                                    Log LSR
                                </Link>
                            </Button>
                        )}
                    </div>
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
                    <Button
                        type="button"
                        size="sm"
                        variant={
                            filters.status === 'open' ? 'default' : 'outline'
                        }
                        onClick={() => applyFilters({ status: 'open' })}
                    >
                        Open
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={
                            filters.status === 'closed' ? 'default' : 'outline'
                        }
                        onClick={() => applyFilters({ status: 'closed' })}
                    >
                        Closed
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-3 py-2">Category</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Worker</th>
                                <th className="px-3 py-2">Occurred</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {violations.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        {row.category_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.status_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.worker_label ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.occurred_at}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Link
                                                    href={`/lsr-violations/${row.id}`}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                            {canClose &&
                                                row.status === 'open' && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() =>
                                                            setCloseId(row.id)
                                                        }
                                                    >
                                                        Close
                                                    </Button>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {violations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No LSR entries.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <Pagination
                        meta={violations.meta}
                        pageUrl="/lsr-violations"
                        params={filters}
                    />
                </div>

                {closeId !== null && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <Form
                            action={`/lsr-violations/${closeId}/close`}
                            method="post"
                            className="w-full max-w-md space-y-3 rounded-lg border border-border bg-background p-4"
                            onSuccess={() => setCloseId(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <h3 className="font-medium">
                                        Close LSR #{closeId}
                                    </h3>
                                    <Label htmlFor="action_taken">
                                        Action taken (required)
                                    </Label>
                                    <Input
                                        id="action_taken"
                                        name="action_taken"
                                        required
                                        minLength={10}
                                    />
                                    {errors.action_taken && (
                                        <p className="text-sm text-destructive">
                                            {errors.action_taken}
                                        </p>
                                    )}
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setCloseId(null)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Close
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                )}

                {categoryOptions.length === 0 && null}
            </div>
        </>
    );
}
