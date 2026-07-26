import { useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

export type WorkerPickerOption = {
    id: number;
    name: string;
};

type Props = {
    workers: WorkerPickerOption[];
    value: number[];
    onChange: (ids: number[]) => void;
    className?: string;
};

/** Searchable multi-select worker picker (DOC-06 §7 access-list manager). */
export function WorkerPicker({ workers, value, onChange, className }: Props) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return workers;
        }

        return workers.filter((worker) =>
            worker.name.toLowerCase().includes(term),
        );
    }, [workers, search]);

    function toggle(id: number): void {
        onChange(
            value.includes(id)
                ? value.filter((existing) => existing !== id)
                : [...value, id],
        );
    }

    return (
        <div className={className}>
            <div className="flex items-center justify-between gap-2">
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search workers…"
                    className="w-full sm:w-64"
                    aria-label="Search workers"
                />
                <span className="shrink-0 text-xs text-text-faint">
                    {value.length} selected
                </span>
            </div>
            <div className="mt-2 max-h-64 overflow-y-auto rounded-[var(--radius-sm)] border border-border bg-surface-2">
                {filtered.map((worker) => (
                    <label
                        key={worker.id}
                        className="flex cursor-pointer items-center gap-2 border-b border-border px-3 py-2 text-sm last:border-0 hover:bg-surface-3"
                    >
                        <Checkbox
                            checked={value.includes(worker.id)}
                            onCheckedChange={() => toggle(worker.id)}
                        />
                        <span className="text-text">{worker.name}</span>
                    </label>
                ))}
                {filtered.length === 0 && (
                    <p className="px-3 py-6 text-center text-sm text-text-faint">
                        No workers match “{search}”.
                    </p>
                )}
            </div>
        </div>
    );
}
