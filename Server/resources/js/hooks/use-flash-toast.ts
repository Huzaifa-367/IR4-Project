import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { useSharedSettings } from '@/hooks/use-auth';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    const { warning_toast_seconds: warningToastSeconds } = useSharedSettings();

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const duration =
                data.type === 'warning' || data.type === 'info'
                    ? Math.max(1, warningToastSeconds) * 1000
                    : undefined;

            toast[data.type](data.message, duration ? { duration } : undefined);
        });
    }, [warningToastSeconds]);
}
