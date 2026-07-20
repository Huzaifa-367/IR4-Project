import { router } from '@inertiajs/react';

export type FilterQuery = Record<
    string,
    string | number | boolean | null | undefined
>;

const DEFAULT_OPTIONS = {
    preserveState: true,
    replace: true,
} as const;

/** Inertia GET visit for list/dashboard filters (preserveState + replace). */
export function visitFilters(
    url: string,
    query: FilterQuery,
    options?: { only?: string[] },
): void {
    router.get(url, query, {
        ...DEFAULT_OPTIONS,
        only: options?.only,
    });
}

export const FILTER_SEARCH_DEBOUNCE_MS = 300;
