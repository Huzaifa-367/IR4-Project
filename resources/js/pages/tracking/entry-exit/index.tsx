import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { visitFilters } from '@/lib/visit-filters';
import type { PaginatedMeta } from '@/types/hardware';

type LogRow = {
    id: number;
    worker_id: number;
    worker_name: string | null;
    direction: string;
    source: string;
    occurred_at: string;
    correction_note: string | null;
    gate_zone: string | null;
};

type Props = {
    logs: { data: LogRow[]; meta: PaginatedMeta };
    filters: { direction: string; source: string; worker_id: string };
    workers: Array<{ id: number; name: string }>;
    canCorrect: boolean;
};

const ALL = 'all';

export default function EntryExitIndex({
    logs,
    filters,
    workers,
    canCorrect,
}: Props) {
    const [direction, setDirection] = useState(filters.direction || ALL);
    const [source, setSource] = useState(filters.source || ALL);
    const [workerId, setWorkerId] = useState(filters.worker_id || ALL);
    const [correctionOpen, setCorrectionOpen] = useState(false);
    const [correctionDirection, setCorrectionDirection] = useState('in');
    const [correctionWorker, setCorrectionWorker] = useState('');

    function applyFilters(
        patch: Partial<{
            direction: string;
            source: string;
            worker_id: string;
        }> = {},
    ): void {
        const nextDirection = patch.direction ?? direction;
        const nextSource = patch.source ?? source;
        const nextWorkerId = patch.worker_id ?? workerId;

        visitFilters('/tracking/entry-exit', {
            direction: nextDirection === ALL ? undefined : nextDirection,
            source: nextSource === ALL ? undefined : nextSource,
            worker_id: nextWorkerId === ALL ? undefined : nextWorkerId,
        });
    }

    const queryParams = {
        direction: direction === ALL ? undefined : direction,
        source: source === ALL ? undefined : source,
        worker_id: workerId === ALL ? undefined : workerId,
    };

    const columns: SettingsColumn<LogRow>[] = [
        {
            key: 'when',
            header: 'When',
            cell: (row) => new Date(row.occurred_at).toLocaleString(),
        },
        { key: 'worker', header: 'Worker', cell: (row) => row.worker_name },
        {
            key: 'direction',
            header: 'Direction',
            cell: (row) => (
                <StatusPill
                    label={row.direction === 'in' ? 'In' : 'Out'}
                    tone={row.direction === 'in' ? 'ok' : 'neutral'}
                />
            ),
        },
        {
            key: 'source',
            header: 'Source',
            cell: (row) =>
                row.source === 'gate_reader'
                    ? 'Gate'
                    : row.source === 'manual_correction'
                      ? 'Manual'
                      : 'Auto sweep',
        },
        {
            key: 'note',
            header: 'Note',
            cell: (row) => row.correction_note ?? '—',
        },
    ];

    return (
        <>
            <Head title="Entry / exit" />
            <SettingsPageShell
                title="Entry / Exit"
                description="Gate and correction history"
                actions={
                    <>
                        {canCorrect && (
                            <Button
                                type="button"
                                onClick={() => {
                                    setCorrectionDirection('in');
                                    setCorrectionWorker('');
                                    setCorrectionOpen(true);
                                }}
                            >
                                <Plus data-icon="inline-start" />
                                Add correction
                            </Button>
                        )}
                        <Button asChild variant="secondary" size="sm">
                            <a href="/tracking/entry-exit/export">CSV export</a>
                        </Button>
                    </>
                }
                filters={
                    <>
                        <Select
                            value={direction}
                            onValueChange={(value) => {
                                setDirection(value);
                                applyFilters({ direction: value });
                            }}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Direction" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All directions
                                    </SelectItem>
                                    <SelectItem value="in">In</SelectItem>
                                    <SelectItem value="out">Out</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            value={source}
                            onValueChange={(value) => {
                                setSource(value);
                                applyFilters({ source: value });
                            }}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Source" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All sources
                                    </SelectItem>
                                    <SelectItem value="gate_reader">
                                        Gate
                                    </SelectItem>
                                    <SelectItem value="manual_correction">
                                        Manual
                                    </SelectItem>
                                    <SelectItem value="auto_sweep">
                                        Auto sweep
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            value={workerId}
                            onValueChange={(value) => {
                                setWorkerId(value);
                                applyFilters({ worker_id: value });
                            }}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Worker" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All workers
                                    </SelectItem>
                                    {workers.map((w) => (
                                        <SelectItem
                                            key={w.id}
                                            value={String(w.id)}
                                        >
                                            {w.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={logs.data}
                    rowKey={(row) => row.id}
                    meta={logs.meta}
                    pageUrl="/tracking/entry-exit"
                    queryParams={queryParams}
                    emptyTitle="No entry/exit logs"
                    emptyDescription="No entry/exit logs match these filters."
                />
            </SettingsPageShell>

            <Link
                href="/tracking"
                className="text-sm text-[color:var(--accent)] hover:underline"
            >
                Back to tracking
            </Link>

            <CrudFormDialog
                open={correctionOpen}
                onOpenChange={setCorrectionOpen}
                title="Add entry/exit correction"
                description="Creates a new manual_correction row; a gate-generated row is never edited."
                action="/tracking/entry-exit/corrections"
                method="post"
                submitLabel="Add correction"
                disableSubmit={!correctionWorker}
                transform={(data) => ({
                    ...data,
                    worker_id: correctionWorker,
                    direction: correctionDirection,
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label>Worker</Label>
                            <Select
                                value={correctionWorker}
                                onValueChange={setCorrectionWorker}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a worker…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {workers.map((w) => (
                                            <SelectItem
                                                key={w.id}
                                                value={String(w.id)}
                                            >
                                                {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Direction</Label>
                            <Select
                                value={correctionDirection}
                                onValueChange={setCorrectionDirection}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="in">In</SelectItem>
                                        <SelectItem value="out">Out</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="occurred_at">Occurred at</Label>
                            <Input
                                id="occurred_at"
                                name="occurred_at"
                                type="datetime-local"
                                required
                            />
                            {errors.occurred_at ? (
                                <p className="text-sm text-destructive">
                                    {errors.occurred_at}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="note">
                                Correction note (min 10 characters)
                            </Label>
                            <Input
                                id="note"
                                name="note"
                                required
                                minLength={10}
                            />
                            {errors.note ? (
                                <p className="text-sm text-destructive">
                                    {errors.note}
                                </p>
                            ) : null}
                        </div>
                    </>
                )}
            </CrudFormDialog>
        </>
    );
}

EntryExitIndex.layout = {
    breadcrumbs: [{ title: 'Entry / Exit', href: '/tracking/entry-exit' }],
};
