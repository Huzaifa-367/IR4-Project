import { useState } from 'react';
import { CheckoutDialog } from '@/components/ir4/checkout-dialog';
import { EquipmentScanner } from '@/components/ir4/equipment-scanner';
import { ReturnDialog } from '@/components/ir4/return-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { resolveScanCustodyFlow } from '@/lib/equipment-custody-flow';
import { lookupEquipmentByToken } from '@/lib/equipment-qr';
import type {
    EquipmentByToken,
    EquipmentWorkerRef,
    EquipmentZoneRef,
} from '@/types/equipment';

type Props = {
    workers: EquipmentWorkerRef[];
    zones: EquipmentZoneRef[];
    canManage: boolean;
};

export function EquipmentScanEntry({ workers, zones, canManage }: Props) {
    const [scanOpen, setScanOpen] = useState(false);
    const [lookingUp, setLookingUp] = useState(false);
    const [lookupError, setLookupError] = useState<string | null>(null);
    const [resolved, setResolved] = useState<EquipmentByToken | null>(null);
    const [checkoutOpen, setCheckoutOpen] = useState(false);
    const [returnOpen, setReturnOpen] = useState(false);

    function resetFlow(): void {
        setLookupError(null);
        setResolved(null);
        setCheckoutOpen(false);
        setReturnOpen(false);
    }

    async function handleToken(qrToken: string): Promise<void> {
        setLookingUp(true);
        setLookupError(null);
        setResolved(null);

        const result = await lookupEquipmentByToken(qrToken);
        setLookingUp(false);

        if (!result.ok) {
            setLookupError(result.message);

            return;
        }

        setResolved(result.data);
        setScanOpen(false);

        if (resolveScanCustodyFlow(result.data) === 'return') {
            setReturnOpen(true);

            return;
        }

        setCheckoutOpen(true);
    }

    if (!canManage) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                    resetFlow();
                    setScanOpen(true);
                }}
            >
                Scan QR
            </Button>

            <Dialog
                open={scanOpen}
                onOpenChange={(open) => {
                    setScanOpen(open);

                    if (!open) {
                        setLookupError(null);
                    }
                }}
            >
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Scan equipment</DialogTitle>
                        <DialogDescription>
                            One scan decides checkout vs return from current
                            custody state.
                        </DialogDescription>
                    </DialogHeader>
                    <EquipmentScanner
                        busy={lookingUp}
                        onToken={(token) => void handleToken(token)}
                    />
                    {lookingUp && (
                        <p className="text-sm text-muted-foreground">
                            Looking up equipment…
                        </p>
                    )}
                    {lookupError && (
                        <p className="text-sm text-destructive">
                            {lookupError}
                        </p>
                    )}
                </DialogContent>
            </Dialog>

            <CheckoutDialog
                open={checkoutOpen}
                onOpenChange={(open) => {
                    setCheckoutOpen(open);

                    if (!open) {
                        setResolved(null);
                    }
                }}
                equipment={resolved}
                workers={workers}
                zones={zones}
            />

            <ReturnDialog
                open={returnOpen}
                onOpenChange={(open) => {
                    setReturnOpen(open);

                    if (!open) {
                        setResolved(null);
                    }
                }}
                checkout={resolved?.open_checkout ?? null}
                equipmentLabel={
                    resolved
                        ? `${resolved.equipment_code} · ${resolved.name}`
                        : undefined
                }
            />
        </>
    );
}
