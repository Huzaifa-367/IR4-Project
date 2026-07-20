import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { MaintenanceType, MaintenanceTypeLabels } from '@/types/enums';

type Props = {
    equipmentId: number;
    onSuccess?: () => void;
};

export function MaintenanceForm({ equipmentId, onSuccess }: Props) {
    return (
        <Form
            action={`/equipment/${equipmentId}/maintenances`}
            method="post"
            className="space-y-3"
            options={{ preserveScroll: true }}
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
                        />
                        {errors.performed_at && (
                            <p className="text-sm text-destructive">
                                {errors.performed_at}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="maintenance_type">Type</Label>
                        <SearchableSelect
                            id="maintenance_type"
                            name="maintenance_type"
                            required
                            defaultValue={MaintenanceType.Preventive}
                            options={Object.values(MaintenanceType).map(
                                (value) => ({
                                    value,
                                    label: MaintenanceTypeLabels[value],
                                }),
                            )}
                        />
                        {errors.maintenance_type && (
                            <p className="text-sm text-destructive">
                                {errors.maintenance_type}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            required
                            rows={3}
                            maxLength={5000}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">
                                {errors.description}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="performed_by_name">Performed by</Label>
                        <Input
                            id="performed_by_name"
                            name="performed_by_name"
                            maxLength={150}
                            placeholder="Technician name"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="next_due">Next due (optional)</Label>
                        <Input id="next_due" name="next_due" type="date" />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
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
