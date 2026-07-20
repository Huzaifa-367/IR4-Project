import { useEffect, useRef } from 'react';

/**
 * Debounce a callback. Always invokes the latest callback instance.
 * Returns `[debouncedFn, cancel]` so callers can flush-cancel on immediate applies.
 */
export function useDebouncedCallback<Args extends unknown[]>(
    callback: (...args: Args) => void,
    delayMs: number,
): [(...args: Args) => void, () => void] {
    const callbackRef = useRef(callback);
    const delayRef = useRef(delayMs);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        callbackRef.current = callback;
    });

    useEffect(() => {
        delayRef.current = delayMs;
    });

    useEffect(() => {
        return () => {
            if (timerRef.current !== null) {
                clearTimeout(timerRef.current);
            }
        };
    }, []);

    const cancel = (): void => {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    };

    const debounced = (...args: Args): void => {
        cancel();
        timerRef.current = setTimeout(() => {
            timerRef.current = null;
            callbackRef.current(...args);
        }, delayRef.current);
    };

    return [debounced, cancel];
}
