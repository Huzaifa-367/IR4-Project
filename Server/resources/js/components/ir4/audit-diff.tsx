import type { AuditDiff as AuditDiffValues } from '@/types/audit';

type Props = {
    oldValues: AuditDiffValues | null;
    newValues: AuditDiffValues | null;
};

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return typeof value === 'string' ? value : JSON.stringify(value);
}

export function AuditDiff({ oldValues, newValues }: Props) {
    const fields: string[] = Array.from(
        new Set([
            ...Object.keys(oldValues ?? {}),
            ...Object.keys(newValues ?? {}),
        ]),
    );

    if (fields.length === 0) {
        return <p className="text-xs text-muted-foreground">No field diff.</p>;
    }

    return (
        <div className="overflow-hidden rounded-md border border-border">
            {fields.map((field: string) => (
                <div
                    key={field}
                    className="grid grid-cols-[minmax(8rem,0.5fr)_1fr_1fr] gap-3 border-b border-border px-3 py-2 text-xs last:border-b-0"
                >
                    <span className="font-mono text-muted-foreground">
                        {field}
                    </span>
                    <span className="break-all text-red-300">
                        {formatValue(oldValues?.[field])}
                    </span>
                    <span className="break-all text-emerald-300">
                        {formatValue(newValues?.[field])}
                    </span>
                </div>
            ))}
        </div>
    );
}
