import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { useSharedSettings } from '@/hooks/use-auth';
import type { FlashToast } from '@/types/ui';

type InertiaFlash = {
    toast?: FlashToast;
};

function showFlashToast(data: FlashToast, warningToastSeconds: number): void {
    const duration =
        data.type === 'warning' || data.type === 'info'
            ? Math.max(1, warningToastSeconds) * 1000
            : undefined;
    const options = duration ? { duration } : undefined;

    switch (data.type) {
        case 'success':
            toast.success(data.message, options);
            break;
        case 'info':
            toast.info(data.message, options);
            break;
        case 'warning':
            toast.warning(data.message, options);
            break;
        case 'error':
            toast.error(data.message, options);
            break;
        default:
            toast.message(data.message, options);
    }
}

/**
 * Inertia 3 one-shot flash lives on `page.flash` (not props). Prefer watching
 * that over `inertia:flash` — the DOM event can race layout mount/HMR.
 */
export function useFlashToast(): void {
    const { warning_toast_seconds: warningToastSeconds } = useSharedSettings();
    const page = usePage();
    const flash = (page as typeof page & { flash?: InertiaFlash }).flash;
    const lastKey = useRef<string | null>(null);

    useEffect(() => {
        const data = flash?.toast;

        if (!data?.message) {
            lastKey.current = null;

            return;
        }

        const key = `${data.type}:${data.message}`;

        if (lastKey.current === key) {
            return;
        }

        lastKey.current = key;
        showFlashToast(data, warningToastSeconds);
    }, [flash, warningToastSeconds]);

    useEffect(() => {
        const stopHttpException = router.on('httpException', () => {
            toast.error('Request failed. Try again.');
        });
        const stopNetworkError = router.on('networkError', () => {
            toast.error('Network error. Try again.');
        });

        return (): void => {
            stopHttpException();
            stopNetworkError();
        };
    }, []);
}
