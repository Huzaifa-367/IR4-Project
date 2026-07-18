import { router } from '@inertiajs/react';
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

/** Working Previous/Next pagination for any paginated list page (DOC-01 §5.5). */
export function Pagination({ meta, pageUrl, params = {}, className }: Props) {
    if (meta.last_page <= 1) {
        return null;
    }

    const go = (page: number): void => {
        router.get(
            pageUrl,
            { ...params, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-3 border-t border-border px-3 py-2 text-xs text-text-dim',
                className,
            )}
        >
            <span>
                Page {meta.current_page} of {meta.last_page} · {meta.total}{' '}
                total
            </span>
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={meta.current_page <= 1}
                    onClick={() => go(meta.current_page - 1)}
                >
                    Previous
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => go(meta.current_page + 1)}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
