import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

type SingleProps = {
    equipmentId: number;
    label?: string;
    size?: 'default' | 'sm' | 'lg' | 'icon';
    variant?: 'default' | 'secondary' | 'outline' | 'ghost';
};

export function QrLabelButton({
    equipmentId,
    label = 'Print QR label',
    size = 'sm',
    variant = 'secondary',
}: SingleProps) {
    function printLabel(): void {
        router.post(
            `/equipment/${equipmentId}/print-label`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Sent to printer'),
                onError: () =>
                    toast.error('Print failed — try again or download ZPL'),
            },
        );
    }

    return (
        <Button
            type="button"
            size={size}
            variant={variant}
            onClick={printLabel}
        >
            {label}
        </Button>
    );
}

type BulkProps = {
    ids: number[];
    label?: string;
    disabled?: boolean;
};

export function QrLabelsBulkButton({
    ids,
    label = 'Print selected',
    disabled = false,
}: BulkProps) {
    function printLabels(): void {
        if (ids.length === 0) {
            toast.message('Select at least one item');

            return;
        }

        router.post(
            '/equipment/print-labels',
            { ids },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(`Sent ${ids.length} label(s) to printer`),
                onError: () => toast.error('Bulk print failed'),
            },
        );
    }

    return (
        <Button
            type="button"
            size="sm"
            variant="secondary"
            disabled={disabled || ids.length === 0}
            onClick={printLabels}
        >
            {label}
            {ids.length > 0 ? ` (${ids.length})` : ''}
        </Button>
    );
}
