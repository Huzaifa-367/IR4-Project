import type { ReactNode } from 'react';
import { Pagination } from '@/components/ir4/pagination';
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

export type SettingsColumn<T> = {
    key: string;
    header: ReactNode;
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
    /** Active filters/sort to preserve when paging (DOC-01 §5.5). */
    queryParams?: Record<string, string | number | boolean | undefined>;
};

export function SettingsDataTable<T>({
    columns,
    rows,
    rowKey,
    emptyTitle = 'No records',
    emptyDescription = 'Nothing registered yet.',
    meta,
    pageUrl,
    queryParams,
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
            {meta && pageUrl ? (
                <Pagination
                    meta={meta}
                    pageUrl={pageUrl}
                    params={queryParams}
                />
            ) : null}
        </div>
    );
}
