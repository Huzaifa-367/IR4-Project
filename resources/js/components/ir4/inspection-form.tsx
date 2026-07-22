import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { cn } from '@/lib/utils';
import { InspectionOutcome, InspectionOutcomeLabels } from '@/types/enums';

type Props = {
    equipmentId: number;
    onSuccess?: () => void;
    className?: string;
};

export function InspectionForm({
    equipmentId,
    onSuccess,
    className,
}: Props) {
    const [outcome, setOutcome] = useState<string>(InspectionOutcome.Pass);

    return (
        <Form
            action={`/equipment/${equipmentId}/inspections`}
            method="post"
            className={cn('space-y-3', className)}
            options={{ preserveScroll: true }}
            transform={(data) => ({
                ...data,
                outcome,
            })}
            onSuccess={onSuccess}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="inspected_at">Inspected at</Label>
                        <Input
                            id="inspected_at"
                            name="inspected_at"
                            type="date"
                            required
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            className="bg-surface"
                        />
                        {errors.inspected_at ? (
                            <p className="text-sm text-destructive">
                                {errors.inspected_at}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="outcome">Outcome</Label>
                        <SearchableSelect
                            id="outcome"
                            required
                            value={outcome}
                            onValueChange={setOutcome}
                            options={Object.values(InspectionOutcome).map(
                                (value) => ({
                                    value,
                                    label: InspectionOutcomeLabels[value],
                                }),
                            )}
                        />
                        {errors.outcome ? (
                            <p className="text-sm text-destructive">
                                {errors.outcome}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows={2}
                            maxLength={5000}
                            className="rounded-md border border-input bg-surface px-3 py-2 text-sm"
                        />
                        {errors.notes ? (
                            <p className="text-sm text-destructive">
                                {errors.notes}
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
                    <Button type="submit" disabled={processing}>
                        Log inspection
                    </Button>
                </>
            )}
        </Form>
    );
}
