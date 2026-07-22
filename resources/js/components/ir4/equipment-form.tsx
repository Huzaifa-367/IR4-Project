import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { cn } from '@/lib/utils';
import { EquipmentStatus, EquipmentStatusLabels } from '@/types/enums';
import type { Equipment } from '@/types/equipment';

type Props = {
    action: string;
    method: 'post' | 'put';
    defaults?: Partial<Equipment>;
    submitLabel: string;
    /** Status is only editable on update (create defaults to in_service). */
    allowStatus?: boolean;
    className?: string;
};

export function EquipmentForm({
    action,
    method,
    defaults = {},
    submitLabel,
    allowStatus = false,
    className,
}: Props) {
    const [status, setStatus] = useState(
        defaults.status ?? EquipmentStatus.InService,
    );

    return (
        <Form
            action={action}
            method={method}
            className={cn('space-y-4', className)}
            options={{ preserveScroll: true }}
            transform={(data) =>
                allowStatus
                    ? {
                          ...data,
                          status,
                      }
                    : data
            }
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="equipment_code">
                                Equipment code
                            </Label>
                            <Input
                                id="equipment_code"
                                name="equipment_code"
                                maxLength={150}
                                defaultValue={defaults.equipment_code ?? ''}
                                placeholder="Leave blank to auto-generate"
                                className="bg-surface"
                            />
                            {errors.equipment_code ? (
                                <p className="text-sm text-destructive">
                                    {errors.equipment_code}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="equipment_type">Type</Label>
                            <Input
                                id="equipment_type"
                                name="equipment_type"
                                required
                                maxLength={150}
                                defaultValue={defaults.equipment_type ?? ''}
                                placeholder="e.g. extinguisher, sling"
                                className="bg-surface"
                            />
                            {errors.equipment_type ? (
                                <p className="text-sm text-destructive">
                                    {errors.equipment_type}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                maxLength={150}
                                defaultValue={defaults.name ?? ''}
                                className="bg-surface"
                            />
                            {errors.name ? (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="location_label">Location</Label>
                            <Input
                                id="location_label"
                                name="location_label"
                                maxLength={150}
                                defaultValue={defaults.location_label ?? ''}
                                className="bg-surface"
                            />
                            {errors.location_label ? (
                                <p className="text-sm text-destructive">
                                    {errors.location_label}
                                </p>
                            ) : null}
                        </div>
                        {allowStatus ? (
                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <SearchableSelect
                                    id="status"
                                    value={status}
                                    onValueChange={(value) =>
                                        setStatus(value as EquipmentStatus)
                                    }
                                    options={Object.values(
                                        EquipmentStatus,
                                    ).map((value) => ({
                                        value,
                                        label: EquipmentStatusLabels[value],
                                    }))}
                                />
                                {errors.status ? (
                                    <p className="text-sm text-destructive">
                                        {errors.status}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows={3}
                                maxLength={5000}
                                defaultValue={defaults.description ?? ''}
                                className="rounded-md border border-input bg-surface px-3 py-2 text-sm"
                            />
                            {errors.description ? (
                                <p className="text-sm text-destructive">
                                    {errors.description}
                                </p>
                            ) : null}
                        </div>
                    </div>
                    <label className="flex items-center gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2 text-sm">
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
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
