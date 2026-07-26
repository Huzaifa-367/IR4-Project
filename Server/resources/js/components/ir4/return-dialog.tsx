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
import { ReturnStatus, ReturnStatusLabels } from '@/types/enums';
import type { EquipmentCheckout } from '@/types/equipment';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    checkout: EquipmentCheckout | null;
    equipmentLabel?: string;
};

type ReturnForm = {
    return_status: string;
    return_reason: string;
    condition_in: string;
};

const emptyForm: ReturnForm = {
    return_status: ReturnStatus.Ok,
    return_reason: '',
    condition_in: '',
};

export function ReturnDialog({
    open,
    onOpenChange,
    checkout,
    equipmentLabel,
}: Props) {
    const form = useForm<ReturnForm>(emptyForm);

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(emptyForm);
        form.clearErrors();
        // Reset only when the dialog opens for a checkout.
        // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional open/checkout gate
    }, [open, checkout?.uuid]);

    const offersMaintenance =
        form.data.return_status === ReturnStatus.Damaged ||
        form.data.return_status === ReturnStatus.NeedsService;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!checkout) {
            return;
        }

        form.post(`/equipment/checkouts/${checkout.uuid}/return`, {
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
                    <DialogTitle>Return equipment</DialogTitle>
                    <DialogDescription>
                        {equipmentLabel ??
                            (checkout
                                ? `Checkout #${checkout.id}`
                                : 'Select a checkout to return.')}
                        {checkout?.worker
                            ? ` · held by ${checkout.worker.name}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>
                {checkout ? (
                    <form className="space-y-3" onSubmit={submit}>
                        <div className="grid gap-2">
                            <Label htmlFor="return_status">Return status</Label>
                            <SearchableSelect
                                id="return_status"
                                value={form.data.return_status}
                                onValueChange={(value) =>
                                    form.setData('return_status', value)
                                }
                                options={Object.values(ReturnStatus).map(
                                    (value) => ({
                                        value,
                                        label: ReturnStatusLabels[value],
                                    }),
                                )}
                            />
                            {form.errors.return_status ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.return_status}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="return_reason">Return reason</Label>
                            <Input
                                id="return_reason"
                                value={form.data.return_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'return_reason',
                                        event.target.value,
                                    )
                                }
                                maxLength={150}
                            />
                            {form.errors.return_reason ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.return_reason}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="condition_in">Condition in</Label>
                            <Input
                                id="condition_in"
                                value={form.data.condition_in}
                                onChange={(event) =>
                                    form.setData(
                                        'condition_in',
                                        event.target.value,
                                    )
                                }
                                maxLength={150}
                                placeholder="e.g. fine"
                            />
                            {form.errors.condition_in ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.condition_in}
                                </p>
                            ) : null}
                        </div>
                        {offersMaintenance ? (
                            <p className="rounded-md border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                                Item marked damaged / needs service — after
                                return, log corrective maintenance on the
                                equipment detail page to set out-of-service if
                                required.
                            </p>
                        ) : null}
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full"
                        >
                            Confirm return
                        </Button>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
