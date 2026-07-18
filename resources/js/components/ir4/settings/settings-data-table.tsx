import type { ReactNode } from 'react';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PaginatedMeta } from '@/types/hardware';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';

export type SettingsColumn<T> = {
    key: string;
    header: string;
    className?: string;
    cell: (row: T) => ReactNode;
};

type Props<T> = {
    columns: SettingsColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    emptyTitle?: string;
    emptyDescription?: string;
    meta?: PaginatedMeta;
    pageUrl?: string;
};

export function SettingsDataTable<T>({
    columns,
    rows,
    rowKey,
    emptyTitle = 'No records',
    emptyDescription = 'Nothing registered yet.',
    meta,
    pageUrl,
}: Props<T>) {
    if (rows.length === 0) {
        return (
            <Empty>
                <EmptyTitle>{emptyTitle}</EmptyTitle>
                <EmptyDescription>{emptyDescription}</EmptyDescription>
            </Empty>
        );
    }

    return (
        <div className="overflow-hidden rounded-[var(--radius-sm)] border border-border bg-surface shadow-[var(--shadow-card)]">
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        {columns.map((column) => (
                            <TableHead
                                key={column.key}
                                className={column.className}
                            >
                                {column.header}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={rowKey(row)}>
                            {columns.map((column) => (
                                <TableCell
                                    key={column.key}
                                    className={column.className}
                                >
                                    {column.cell(row)}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {meta && pageUrl && meta.last_page > 1 ? (
                <div className="flex items-center justify-between gap-3 border-t border-border px-3 py-2 text-xs text-text-dim">
                    <span>
                        Page {meta.current_page} of {meta.last_page} ·{' '}
                        {meta.total} total
                    </span>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={meta.current_page <= 1}
                            onClick={() =>
                                router.get(
                                    pageUrl,
                                    { page: meta.current_page - 1 },
                                    { preserveState: true, preserveScroll: true },
                                )
                            }
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() =>
                                router.get(
                                    pageUrl,
                                    { page: meta.current_page + 1 },
                                    { preserveState: true, preserveScroll: true },
                                )
                            }
                        >
                            Next
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
