import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import type {
    EquipmentByToken,
    EquipmentWorkerRef,
    EquipmentZoneRef,
} from '@/types/equipment';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    equipment: EquipmentByToken | null;
    workers: EquipmentWorkerRef[];
    zones: EquipmentZoneRef[];
};

type CheckoutForm = {
    worker_id: string;
    reason: string;
    zone_id: string;
    expected_return_at: string;
    condition_out: string;
};

const emptyForm: CheckoutForm = {
    worker_id: '',
    reason: '',
    zone_id: '',
    expected_return_at: '',
    condition_out: '',
};

export function CheckoutDialog({
    open,
    onOpenChange,
    equipment,
    workers,
    zones,
}: Props) {
    const form = useForm<CheckoutForm>(emptyForm);

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(emptyForm);
        form.clearErrors();
        // Reset only when the dialog opens for an item.
        // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional open/equipment gate
    }, [open, equipment?.uuid]);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!equipment) {
            return;
        }

        if (form.data.worker_id === '') {
            form.setError('worker_id', 'Select a worker.');

            return;
        }

        form.post(`/equipment/${equipment.uuid}/checkout`, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
                form.clearErrors();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Check out equipment</DialogTitle>
                    <DialogDescription>
                        {equipment
                            ? `${equipment.equipment_code} · ${equipment.name}`
                            : 'Select equipment to check out.'}
                    </DialogDescription>
                </DialogHeader>
                {equipment ? (
                    <form className="space-y-3" onSubmit={submit}>
                        <div className="grid gap-2">
                            <Label htmlFor="worker_id">Worker</Label>
                            <SearchableSelect
                                id="worker_id"
                                required
                                value={form.data.worker_id}
                                onValueChange={(value) => {
                                    form.setData('worker_id', value);
                                    form.clearErrors('worker_id');
                                }}
                                placeholder="Select worker…"
                                options={workers.map((worker) => ({
                                    value: String(worker.id),
                                    label: worker.name,
                                }))}
                            />
                            {form.errors.worker_id ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.worker_id}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Reason / task</Label>
                            <Input
                                id="reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                maxLength={150}
                            />
                            {form.errors.reason ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.reason}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="zone_id">Zone (optional)</Label>
                            <SearchableSelect
                                id="zone_id"
                                value={form.data.zone_id}
                                onValueChange={(value) =>
                                    form.setData('zone_id', value)
                                }
                                allowClear
                                clearLabel="None"
                                placeholder="None"
                                options={zones.map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.name,
                                }))}
                            />
                            {form.errors.zone_id ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.zone_id}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="expected_return_at">
                                Expected return
                            </Label>
                            <Input
                                id="expected_return_at"
                                type="datetime-local"
                                value={form.data.expected_return_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expected_return_at',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.expected_return_at ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.expected_return_at}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="condition_out">Condition out</Label>
                            <Input
                                id="condition_out"
                                value={form.data.condition_out}
                                onChange={(event) =>
                                    form.setData(
                                        'condition_out',
                                        event.target.value,
                                    )
                                }
                                maxLength={150}
                                placeholder="e.g. good"
                            />
                            {form.errors.condition_out ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.condition_out}
                                </p>
                            ) : null}
                        </div>
                        {(() => {
                            const equipmentError = (
                                form.errors as Partial<Record<string, string>>
                            ).equipment;

                            return equipmentError ? (
                                <p className="text-sm text-destructive">
                                    {equipmentError}
                                </p>
                            ) : null;
                        })()}
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full"
                        >
                            Confirm checkout
                        </Button>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
