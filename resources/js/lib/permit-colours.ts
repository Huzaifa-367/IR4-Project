/** GI / SA form colour tokens used on permit types (DOC-22). */

export const PERMIT_TYPE_COLOUR_DOT: Record<string, string> = {
    red: 'bg-red-500',
    blue: 'bg-blue-500',
    green: 'bg-emerald-500',
    yellow: 'bg-amber-400',
    orange: 'bg-orange-500',
    purple: 'bg-violet-500',
    cyan: 'bg-cyan-500',
};

export const PERMIT_TYPE_COLOUR_BAR: Record<string, string> = {
    red: 'bg-red-500',
    blue: 'bg-blue-500',
    green: 'bg-emerald-500',
    yellow: 'bg-amber-400',
    orange: 'bg-orange-500',
    purple: 'bg-violet-500',
    cyan: 'bg-cyan-500',
};

export const PERMIT_TYPE_COLOUR_SOFT: Record<string, string> = {
    red: 'bg-red-500/10 text-red-700 dark:text-red-300',
    blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    yellow: 'bg-amber-500/10 text-amber-800 dark:text-amber-200',
    orange: 'bg-orange-500/10 text-orange-800 dark:text-orange-200',
    purple: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    cyan: 'bg-cyan-500/10 text-cyan-800 dark:text-cyan-200',
};

export function permitTypeDotClass(token: string | null | undefined): string {
    if (!token) {
        return 'bg-text-faint';
    }

    return PERMIT_TYPE_COLOUR_DOT[token] ?? 'bg-text-faint';
}

export function permitTypeBarClass(token: string | null | undefined): string {
    if (!token) {
        return 'bg-[color:var(--accent)]';
    }

    return PERMIT_TYPE_COLOUR_BAR[token] ?? 'bg-[color:var(--accent)]';
}

export function permitTypeSoftClass(token: string | null | undefined): string {
    if (!token) {
        return 'bg-surface-3 text-text-dim';
    }

    return (
        PERMIT_TYPE_COLOUR_SOFT[token] ?? 'bg-surface-3 text-text-dim'
    );
}
