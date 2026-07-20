import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PlainDeviceToken } from '@/types/hardware';

type Props = {
    token: PlainDeviceToken | null;
    onDismiss: () => void;
};

export function TokenRevealDialog({ token, onDismiss }: Props) {
    const [acknowledged, setAcknowledged] = useState(false);

    return (
        <Dialog
            open={token !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setAcknowledged(false);
                    onDismiss();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Copy device token</DialogTitle>
                    <DialogDescription>
                        {token
                            ? `Token for ${token.device_name}. It will not be shown again.`
                            : ''}
                    </DialogDescription>
                </DialogHeader>
                {token ? (
                    <div className="rounded-[var(--radius-sm)] border border-border bg-surface-2 p-3">
                        <code className="block font-mono text-xs break-all text-text">
                            {token.token}
                        </code>
                    </div>
                ) : null}
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={async () => {
                            if (!token) {
                                return;
                            }

                            await navigator.clipboard.writeText(token.token);
                            toast.success('Token copied');
                        }}
                    >
                        Copy
                    </Button>
                    <Button
                        type="button"
                        disabled={!acknowledged && false}
                        onClick={() => {
                            setAcknowledged(true);
                            onDismiss();
                        }}
                    >
                        I have stored it
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
