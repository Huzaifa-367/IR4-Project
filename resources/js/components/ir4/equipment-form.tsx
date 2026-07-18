import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { EquipmentStatus, EquipmentStatusLabels } from '@/types/enums';
import type { Equipment } from '@/types/equipment';

type Props = {
    action: string;
    method: 'post' | 'put';
    defaults?: Partial<Equipment>;
    submitLabel: string;
    /** Status is only editable on update (create defaults to in_service). */
    allowStatus?: boolean;
};

export function EquipmentForm({
    action,
    method,
    defaults = {},
    submitLabel,
    allowStatus = false,
}: Props) {
    return (
        <Form
            action={action}
            method={method}
            className="space-y-4"
            options={{ preserveScroll: true }}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="equipment_code">Equipment code</Label>
                        <Input
                            id="equipment_code"
                            name="equipment_code"
                            maxLength={150}
                            defaultValue={defaults.equipment_code ?? ''}
                            placeholder="Leave blank to auto-generate"
                        />
                        {errors.equipment_code && (
                            <p className="text-sm text-destructive">
                                {errors.equipment_code}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={150}
                            defaultValue={defaults.name ?? ''}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="equipment_type">Type</Label>
                        <Input
                            id="equipment_type"
                            name="equipment_type"
                            required
                            maxLength={150}
                            defaultValue={defaults.equipment_type ?? ''}
                            placeholder="e.g. extinguisher, sling, generator"
                        />
                        {errors.equipment_type && (
                            <p className="text-sm text-destructive">
                                {errors.equipment_type}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="location_label">Location</Label>
                        <Input
                            id="location_label"
                            name="location_label"
                            maxLength={150}
                            defaultValue={defaults.location_label ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows={3}
                            maxLength={5000}
                            defaultValue={defaults.description ?? ''}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_checkoutable" value="0" />
                        <input
                            type="checkbox"
                            name="is_checkoutable"
                            value="1"
                            defaultChecked={defaults.is_checkoutable ?? false}
                            className="size-4 rounded border"
                        />
                        Checkoutable (participates in custody tracking)
                    </label>
                    {allowStatus && (
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                defaultValue={
                                    defaults.status ?? EquipmentStatus.InService
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {Object.values(EquipmentStatus).map((value) => (
                                    <option key={value} value={value}>
                                        {EquipmentStatusLabels[value]}
                                    </option>
                                ))}
                            </select>
                            {errors.status && (
                                <p className="text-sm text-destructive">
                                    {errors.status}
                                </p>
                            )}
                        </div>
                    )}
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
