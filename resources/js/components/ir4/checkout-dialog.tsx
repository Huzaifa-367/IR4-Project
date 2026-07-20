import { Form } from '@inertiajs/react';
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

export function CheckoutDialog({
    open,
    onOpenChange,
    equipment,
    workers,
    zones,
}: Props) {
    if (!equipment) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Check out equipment</DialogTitle>
                    <DialogDescription>
                        {equipment.equipment_code} · {equipment.name}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/equipment/${equipment.id}/checkout`}
                    method="post"
                    className="space-y-3"
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="worker_id">Worker</Label>
                                <SearchableSelect
                                    id="worker_id"
                                    name="worker_id"
                                    required
                                    defaultValue=""
                                    placeholder="Select worker…"
                                    options={workers.map((worker) => ({
                                        value: String(worker.id),
                                        label: worker.name,
                                    }))}
                                />
                                {errors.worker_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.worker_id}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reason">Reason / task</Label>
                                <Input
                                    id="reason"
                                    name="reason"
                                    maxLength={500}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="zone_id">Zone (optional)</Label>
                                <SearchableSelect
                                    id="zone_id"
                                    name="zone_id"
                                    defaultValue=""
                                    allowClear
                                    clearLabel="None"
                                    placeholder="None"
                                    options={zones.map((zone) => ({
                                        value: String(zone.id),
                                        label: zone.name,
                                    }))}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expected_return_at">
                                    Expected return
                                </Label>
                                <Input
                                    id="expected_return_at"
                                    name="expected_return_at"
                                    type="datetime-local"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="condition_out">
                                    Condition out
                                </Label>
                                <Input
                                    id="condition_out"
                                    name="condition_out"
                                    maxLength={500}
                                    placeholder="e.g. good"
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                Confirm checkout
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
