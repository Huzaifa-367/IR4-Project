import { router } from '@inertiajs/react';
import { ChevronDown, Download, Printer } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type QrFormat = 'png' | 'svg' | 'zpl';

type SingleProps = {
    equipmentId: number;
    label?: string;
    size?: 'default' | 'sm' | 'lg' | 'icon';
    variant?: 'default' | 'secondary' | 'outline' | 'ghost';
};

export function QrLabelButton({
    equipmentId,
    label = 'QR label',
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

    function downloadQr(format: QrFormat): void {
        const link = document.createElement('a');
        link.href = `/equipment/${equipmentId}/qr?format=${format}`;
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();

        const labels: Record<QrFormat, string> = {
            png: 'Downloading QR image…',
            svg: 'Downloading QR SVG…',
            zpl: 'Downloading ZPL…',
        };
        toast.success(labels[format]);
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" size={size} variant={variant}>
                    {label}
                    <ChevronDown className="size-3.5 opacity-70" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem onSelect={printLabel}>
                    <Printer />
                    Print label
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => downloadQr('png')}>
                    <Download />
                    Download PNG
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => downloadQr('svg')}>
                    <Download />
                    Download SVG
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => downloadQr('zpl')}>
                    <Download />
                    Download ZPL
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
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
