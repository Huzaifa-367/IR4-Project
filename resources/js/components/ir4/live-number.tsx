import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    value: number | string;
    className?: string;
    durationMs?: number;
};

/**
 * Tabular live figure with a short tween when the numeric value changes.
 */
export function LiveNumber({
    value,
    className,
    durationMs = 200,
}: Props) {
    const numeric = typeof value === 'number' ? value : Number(value);
    const isNumeric = Number.isFinite(numeric);
    const [display, setDisplay] = useState<string>(String(value));
    const fromRef = useRef(isNumeric ? numeric : 0);
    const reducedMotion =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    useEffect(() => {
        if (!isNumeric || reducedMotion) {
            setDisplay(String(value));

            if (isNumeric) {
                fromRef.current = numeric;
            }

            return;
        }

        const from = fromRef.current;
        const to = numeric;

        if (from === to) {
            setDisplay(String(to));

            return;
        }

        const start = performance.now();
        let frame = 0;

        const tick = (now: number) => {
            const t = Math.min(1, (now - start) / durationMs);
            const eased = 1 - (1 - t) * (1 - t);
            const current = from + (to - from) * eased;
            setDisplay(
                Number.isInteger(to) ? String(Math.round(current)) : current.toFixed(1),
            );

            if (t < 1) {
                frame = requestAnimationFrame(tick);
            } else {
                fromRef.current = to;
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [durationMs, isNumeric, numeric, reducedMotion, value]);

    return (
        <span
            className={cn(
                'font-display font-semibold tracking-tight tabular-nums',
                className,
            )}
        >
            {display}
        </span>
    );
}
