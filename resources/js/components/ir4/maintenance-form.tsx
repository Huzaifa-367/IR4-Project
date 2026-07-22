import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { cn } from '@/lib/utils';
import { MaintenanceType, MaintenanceTypeLabels } from '@/types/enums';

type Props = {
    equipmentId: number;
    onSuccess?: () => void;
    className?: string;
};

export function MaintenanceForm({
    equipmentId,
    onSuccess,
    className,
}: Props) {
    const [maintenanceType, setMaintenanceType] = useState<string>(
        MaintenanceType.Preventive,
    );

    return (
        <Form
            action={`/equipment/${equipmentId}/maintenances`}
            method="post"
            className={cn('space-y-3', className)}
            options={{ preserveScroll: true }}
            transform={(data) => ({
                ...data,
                maintenance_type: maintenanceType,
            })}
            onSuccess={onSuccess}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="performed_at">Performed at</Label>
                        <Input
                            id="performed_at"
                            name="performed_at"
                            type="date"
                            required
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            className="bg-surface"
                        />
                        {errors.performed_at ? (
                            <p className="text-sm text-destructive">
                                {errors.performed_at}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="maintenance_type">Type</Label>
                        <SearchableSelect
                            id="maintenance_type"
                            required
                            value={maintenanceType}
                            onValueChange={setMaintenanceType}
                            options={Object.values(MaintenanceType).map(
                                (value) => ({
                                    value,
                                    label: MaintenanceTypeLabels[value],
                                }),
                            )}
                        />
                        {errors.maintenance_type ? (
                            <p className="text-sm text-destructive">
                                {errors.maintenance_type}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            required
                            rows={3}
                            maxLength={5000}
                            className="rounded-md border border-input bg-surface px-3 py-2 text-sm"
                        />
                        {errors.description ? (
                            <p className="text-sm text-destructive">
                                {errors.description}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="performed_by_name">Performed by</Label>
                        <Input
                            id="performed_by_name"
                            name="performed_by_name"
                            maxLength={150}
                            placeholder="Technician name"
                            className="bg-surface"
                        />
                        {errors.performed_by_name ? (
                            <p className="text-sm text-destructive">
                                {errors.performed_by_name}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="next_due">Next due (optional)</Label>
                        <Input
                            id="next_due"
                            name="next_due"
                            type="date"
                            className="bg-surface"
                        />
                        {errors.next_due ? (
                            <p className="text-sm text-destructive">
                                {errors.next_due}
                            </p>
                        ) : null}
                    </div>
                    <label className="flex items-center gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2 text-sm">
                        <input
                            type="checkbox"
                            name="return_to_service"
                            value="1"
                            className="size-4 rounded border"
                        />
                        Return to service (corrective)
                    </label>
                    <Button type="submit" disabled={processing}>
                        Log maintenance
                    </Button>
                </>
            )}
        </Form>
    );
}
