import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SettingSchema } from '@/types/settings';

type Props = {
    open: boolean;
    setting: SettingSchema | null;
    nextValue: string | number | boolean | null;
    onCancel: () => void;
    onConfirm: () => void;
};

export function SensitiveSettingConfirm({
    open,
    setting,
    nextValue,
    onCancel,
    onConfirm,
}: Props) {
    if (setting === null) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onCancel();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm sensitive change</DialogTitle>
                    <DialogDescription>
                        You are changing a security or retention setting. Review
                        the old and new values before saving.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-2 text-sm">
                    <p className="font-medium">{setting.label}</p>
                    <p className="text-muted-foreground font-mono text-xs">
                        {setting.key}
                    </p>
                    <div className="grid grid-cols-2 gap-3 rounded-lg border border-border p-3">
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">
                                Current
                            </p>
                            <p className="font-mono">
                                {formatValue(setting.value)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">
                                New
                            </p>
                            <p className="font-mono">
                                {formatValue(nextValue)}
                            </p>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={onConfirm}>
                        Confirm change
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function formatValue(value: string | number | boolean | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    return String(value);
}
