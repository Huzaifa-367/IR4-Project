import type {
    PermitBoardCard,
    PermitBoardColumnKey,
    PermitBoardColumns,
} from '@/types/permit';

export const PERMIT_BOARD_COLUMNS: ReadonlyArray<{
    key: PermitBoardColumnKey;
    title: string;
}> = [
    { key: 'pending_inspection', title: 'Pending inspection' },
    { key: 'pending_gas_test', title: 'Gas test' },
    { key: 'pending_issue', title: 'Pending issue' },
    { key: 'active', title: 'Active' },
    { key: 'suspended', title: 'Suspended' },
    { key: 'expiring', title: 'Expiring' },
];

const COLUMN_KEYS: PermitBoardColumnKey[] = PERMIT_BOARD_COLUMNS.map(
    (column) => column.key,
);

export function emptyPermitBoardColumns(): PermitBoardColumns {
    return {
        pending_inspection: [],
        pending_gas_test: [],
        pending_issue: [],
        active: [],
        suspended: [],
        expiring: [],
    };
}

export function isPermitBoardColumnKey(
    value: string,
): value is PermitBoardColumnKey {
    return COLUMN_KEYS.includes(value as PermitBoardColumnKey);
}

export function upsertPermitBoardCard(
    columns: PermitBoardColumns,
    card: PermitBoardCard,
): PermitBoardColumns {
    const next = emptyPermitBoardColumns();

    for (const key of COLUMN_KEYS) {
        next[key] = columns[key].filter((row) => row.uuid !== card.uuid);
    }

    if (isPermitBoardColumnKey(card.column)) {
        next[card.column] = [...next[card.column], card].sort(
            compareBoardCards,
        );
    }

    return next;
}

export function replacePermitBoardColumns(
    data: unknown,
): PermitBoardColumns | null {
    if (data === null || typeof data !== 'object') {
        return null;
    }

    const root = data as { data?: { columns?: unknown }; columns?: unknown };
    const raw = root.data?.columns ?? root.columns;

    if (raw === null || typeof raw !== 'object') {
        return null;
    }

    const source = raw as Record<string, unknown>;
    const next = emptyPermitBoardColumns();

    for (const key of COLUMN_KEYS) {
        const rows = source[key];

        if (!Array.isArray(rows)) {
            continue;
        }

        next[key] = rows.filter(isPermitBoardCard).sort(compareBoardCards);
    }

    return next;
}

function isPermitBoardCard(value: unknown): value is PermitBoardCard {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    const row = value as Partial<PermitBoardCard>;

    return (
        typeof row.uuid === 'string' && typeof row.permit_number === 'string'
    );
}

function compareBoardCards(a: PermitBoardCard, b: PermitBoardCard): number {
    const aTo = a.valid_to ?? '';
    const bTo = b.valid_to ?? '';

    if (aTo !== bTo) {
        return aTo.localeCompare(bTo);
    }

    return a.permit_number.localeCompare(b.permit_number);
}
