import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScheduleType, ScheduleTypeLabels } from '@/types/enums';
import type { MaintenanceSchedule } from '@/types/equipment';

type Props = {
    equipmentId: number;
    schedules: MaintenanceSchedule[];
};

type DraftRow = {
    schedule_type: (typeof ScheduleType)[keyof typeof ScheduleType];
    interval_days: string;
    notes: string;
};

function buildDrafts(schedules: MaintenanceSchedule[]): DraftRow[] {
    return Object.values(ScheduleType).map((type) => {
        const found = schedules.find((row) => row.schedule_type === type);

        return {
            schedule_type: type,
            interval_days: found ? String(found.interval_days) : '',
            notes: found?.notes ?? '',
        };
    });
}

export function ScheduleEditor({ equipmentId, schedules }: Props) {
    const [rows, setRows] = useState<DraftRow[]>(() => buildDrafts(schedules));
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    function updateRow(index: number, patch: Partial<DraftRow>): void {
        setRows((current) =>
            current.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function submit(): void {
        setError(null);
        const payload = rows
            .filter((row) => row.interval_days.trim() !== '')
            .map((row) => ({
                schedule_type: row.schedule_type,
                interval_days: Number(row.interval_days),
                notes: row.notes.trim() === '' ? null : row.notes.trim(),
            }));

        if (payload.length === 0) {
            setError('Set at least one schedule interval (days).');

            return;
        }

        if (
            payload.some(
                (row) =>
                    !Number.isFinite(row.interval_days) ||
                    row.interval_days < 1,
            )
        ) {
            setError('Interval days must be a positive number.');

            return;
        }

        setProcessing(true);
        router.put(
            `/equipment/${equipmentId}/schedules`,
            { schedules: payload },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onError: () => setError('Could not save schedules.'),
            },
        );
    }

    return (
        <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
                Interval days drive next-due recompute after inspections and
                services. Leave a type blank to omit it from the save (existing
                omitted types are removed).
            </p>
            {rows.map((row, index) => (
                <fieldset
                    key={row.schedule_type}
                    className="space-y-2 rounded-md border border-border p-3"
                >
                    <legend className="px-1 text-sm font-medium">
                        {ScheduleTypeLabels[row.schedule_type]}
                    </legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor={`interval-${row.schedule_type}`}>
                                Interval (days)
                            </Label>
                            <Input
                                id={`interval-${row.schedule_type}`}
                                type="number"
                                min={1}
                                value={row.interval_days}
                                onChange={(event) =>
                                    updateRow(index, {
                                        interval_days: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`notes-${row.schedule_type}`}>
                                Notes
                            </Label>
                            <Input
                                id={`notes-${row.schedule_type}`}
                                maxLength={150}
                                value={row.notes}
                                onChange={(event) =>
                                    updateRow(index, {
                                        notes: event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                </fieldset>
            ))}
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="button" disabled={processing} onClick={submit}>
                Save schedules
            </Button>
        </div>
    );
}
