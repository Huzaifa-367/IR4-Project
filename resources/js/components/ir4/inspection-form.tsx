import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InspectionOutcome, InspectionOutcomeLabels } from '@/types/enums';

type Props = {
    equipmentId: number;
    onSuccess?: () => void;
};

export function InspectionForm({ equipmentId, onSuccess }: Props) {
    return (
        <Form
            action={`/equipment/${equipmentId}/inspections`}
            method="post"
            className="space-y-3"
            options={{ preserveScroll: true }}
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
                        />
                        {errors.inspected_at && (
                            <p className="text-sm text-destructive">
                                {errors.inspected_at}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="outcome">Outcome</Label>
                        <select
                            id="outcome"
                            name="outcome"
                            required
                            defaultValue={InspectionOutcome.Pass}
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            {Object.values(InspectionOutcome).map((value) => (
                                <option key={value} value={value}>
                                    {InspectionOutcomeLabels[value]}
                                </option>
                            ))}
                        </select>
                        {errors.outcome && (
                            <p className="text-sm text-destructive">
                                {errors.outcome}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows={2}
                            maxLength={5000}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="next_due">Next due (optional)</Label>
                        <Input id="next_due" name="next_due" type="date" />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Log inspection
                    </Button>
                </>
            )}
        </Form>
    );
}
