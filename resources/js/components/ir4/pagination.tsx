import { router } from '@inertiajs/react';
import { ChevronFirst, ChevronLast, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PaginatedMeta } from '@/types/hardware';

type Props = {
    meta: PaginatedMeta;
    pageUrl: string;
    /** Active filters/sort to preserve across page changes. */
    params?: Record<string, string | number | boolean | undefined>;
    className?: string;
};

type PageToken = number | 'ellipsis';

/** first, last, current, and one neighbour on each side; gaps collapse to an ellipsis. */
function buildPageTokens(current: number, last: number): PageToken[] {
    const keep = new Set<number>([1, last, current, current - 1, current + 1]);
    const pages = [...keep].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);

    const tokens: PageToken[] = [];
    let previous: number | null = null;

    for (const page of pages) {
        if (previous !== null && page - previous > 1) {
            tokens.push('ellipsis');
        }

        tokens.push(page);
        previous = page;
    }

    return tokens;
}

/** User-friendly pagination — numbered pages, first/last jump, and a "Showing X–Y of Z" summary. */
export function Pagination({ meta, pageUrl, params = {}, className }: Props) {
    if (meta.last_page <= 1) {
        return null;
    }

    const go = (page: number): void => {
        if (page < 1 || page > meta.last_page || page === meta.current_page) {
            return;
        }

        router.get(
            pageUrl,
            { ...params, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const rangeStart = meta.per_page
        ? (meta.current_page - 1) * meta.per_page + 1
        : null;
    const rangeEnd = meta.per_page
        ? Math.min(meta.current_page * meta.per_page, meta.total)
        : null;

    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 border-t border-border px-3 py-2.5 text-xs text-text-dim',
                className,
            )}
        >
            <span>
                {rangeStart !== null && rangeEnd !== null ? (
                    <>
                        Showing{' '}
                        <span className="font-mono tabular-nums text-text-dim">
                            {rangeStart}–{rangeEnd}
                        </span>{' '}
                        of{' '}
                        <span className="font-mono tabular-nums text-text-dim">
                            {meta.total}
                        </span>
                    </>
                ) : (
                    <>
                        Page {meta.current_page} of {meta.last_page} ·{' '}
                        {meta.total} total
                    </>
                )}
            </span>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-8"
                    aria-label="First page"
                    disabled={meta.current_page <= 1}
                    onClick={() => go(1)}
                >
                    <ChevronFirst className="size-3.5" />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-8"
                    aria-label="Previous page"
                    disabled={meta.current_page <= 1}
                    onClick={() => go(meta.current_page - 1)}
                >
                    <ChevronLeft className="size-3.5" />
                </Button>
                <div className="mx-1 hidden items-center gap-1 sm:flex">
                    {buildPageTokens(meta.current_page, meta.last_page).map(
                        (token, index) =>
                            token === 'ellipsis' ? (
                                <span
                                    key={`ellipsis-${index}`}
                                    className="px-1.5 text-text-faint"
                                >
                                    …
                                </span>
                            ) : (
                                <Button
                                    key={token}
                                    type="button"
                                    size="icon"
                                    variant={
                                        token === meta.current_page
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="size-8 font-mono tabular-nums"
                                    aria-label={`Page ${token}`}
                                    aria-current={
                                        token === meta.current_page
                                            ? 'page'
                                            : undefined
                                    }
                                    onClick={() => go(token)}
                                >
                                    {token}
                                </Button>
                            ),
                    )}
                </div>
                <span className="mx-1 font-mono text-text-dim sm:hidden">
                    {meta.current_page} / {meta.last_page}
                </span>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-8"
                    aria-label="Next page"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => go(meta.current_page + 1)}
                >
                    <ChevronRight className="size-3.5" />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-8"
                    aria-label="Last page"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => go(meta.last_page)}
                >
                    <ChevronLast className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}
