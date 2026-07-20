import { Form } from '@inertiajs/react';
import { useState } from 'react';
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
import { ReturnStatus, ReturnStatusLabels } from '@/types/enums';
import type { EquipmentCheckout } from '@/types/equipment';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    checkout: EquipmentCheckout | null;
    equipmentLabel?: string;
};

export function ReturnDialog({
    open,
    onOpenChange,
    checkout,
    equipmentLabel,
}: Props) {
    const [returnStatus, setReturnStatus] = useState<string>(ReturnStatus.Ok);

    if (!checkout) {
        return null;
    }

    const offersMaintenance =
        returnStatus === ReturnStatus.Damaged ||
        returnStatus === ReturnStatus.NeedsService;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Return equipment</DialogTitle>
                    <DialogDescription>
                        {equipmentLabel ?? `Checkout #${checkout.id}`}
                        {checkout.worker
                            ? ` · held by ${checkout.worker.name}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/equipment/checkouts/${checkout.id}/return`}
                    method="post"
                    className="space-y-3"
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="return_status">
                                    Return status
                                </Label>
                                <SearchableSelect
                                    id="return_status"
                                    name="return_status"
                                    value={returnStatus}
                                    onValueChange={setReturnStatus}
                                    options={Object.values(ReturnStatus).map(
                                        (value) => ({
                                            value,
                                            label: ReturnStatusLabels[value],
                                        }),
                                    )}
                                />
                                {errors.return_status && (
                                    <p className="text-sm text-destructive">
                                        {errors.return_status}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="return_reason">
                                    Return reason
                                </Label>
                                <Input
                                    id="return_reason"
                                    name="return_reason"
                                    maxLength={150}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="condition_in">
                                    Condition in
                                </Label>
                                <Input
                                    id="condition_in"
                                    name="condition_in"
                                    maxLength={150}
                                    placeholder="e.g. fine"
                                />
                            </div>
                            {offersMaintenance && (
                                <p className="rounded-md border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                                    Item marked damaged / needs service — after
                                    return, log corrective maintenance on the
                                    equipment detail page to set out-of-service
                                    if required.
                                </p>
                            )}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                Confirm return
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
