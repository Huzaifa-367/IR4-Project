/** Shared formatters for weekly report UI + summary maths. */

export function labelize(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** Compact date for wide tables (weekday + day/month). */
export function formatDateCompact(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

export function formatMinAvgMax(
    stats: { min?: unknown; avg?: unknown; max?: unknown } | null | undefined,
    digits = 1,
): string {
    if (!stats) {
        return '—';
    }

    const min = typeof stats.min === 'number' ? stats.min : null;
    const avg = typeof stats.avg === 'number' ? stats.avg : null;
    const max = typeof stats.max === 'number' ? stats.max : null;

    if (min === null && avg === null && max === null) {
        return '—';
    }

    return `${formatNumber(min, digits)} / ${formatNumber(avg, digits)} / ${formatNumber(max, digits)}`;
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatNumber(
    value: number | null | undefined,
    digits = 1,
): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return Number.isInteger(value)
        ? String(value)
        : value.toFixed(digits);
}

export function sumBy<T>(
    rows: T[],
    pick: (row: T) => number | null | undefined,
): number {
    return rows.reduce((total, row) => total + (pick(row) ?? 0), 0);
}

export function avgOf(
    values: Array<number | null | undefined>,
): number | null {
    const nums = values.filter(
        (value): value is number =>
            typeof value === 'number' && !Number.isNaN(value),
    );

    if (nums.length === 0) {
        return null;
    }

    return nums.reduce((a, b) => a + b, 0) / nums.length;
}

export function minOf(
    values: Array<number | null | undefined>,
): number | null {
    const nums = values.filter(
        (value): value is number =>
            typeof value === 'number' && !Number.isNaN(value),
    );

    return nums.length === 0 ? null : Math.min(...nums);
}

export function maxOf(
    values: Array<number | null | undefined>,
): number | null {
    const nums = values.filter(
        (value): value is number =>
            typeof value === 'number' && !Number.isNaN(value),
    );

    return nums.length === 0 ? null : Math.max(...nums);
}

export function byTypeSummary(
    byType: Record<string, number> | undefined,
    limit = 3,
): string {
    if (!byType || Object.keys(byType).length === 0) {
        return '—';
    }

    return Object.entries(byType)
        .filter(([, count]) => count > 0)
        .sort((a, b) => b[1] - a[1])
        .slice(0, limit)
        .map(([type, count]) => `${labelize(type)} (${count})`)
        .join(', ');
}

/** Merge per-day by_type maps into one totals map. */
export function mergeCounts(
    maps: Array<Record<string, number> | undefined>,
): Record<string, number> {
    const out: Record<string, number> = {};

    for (const map of maps) {
        if (!map) {
            continue;
        }

        for (const [key, count] of Object.entries(map)) {
            out[key] = (out[key] ?? 0) + count;
        }
    }

    return out;
}
