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
    const [tween, setTween] = useState<string | null>(null);
    const fromRef = useRef(isNumeric ? numeric : 0);
    const [reducedMotion] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    });

    useEffect(() => {
        if (!isNumeric || reducedMotion) {
            if (isNumeric) {
                fromRef.current = numeric;
            }

            return;
        }

        const from = fromRef.current;
        const to = numeric;

        if (from === to) {
            return;
        }

        const start = performance.now();
        let frame = 0;

        const tick = (now: number): void => {
            const t = Math.min(1, (now - start) / durationMs);
            const eased = 1 - (1 - t) * (1 - t);
            const current = from + (to - from) * eased;
            setTween(
                Number.isInteger(to)
                    ? String(Math.round(current))
                    : current.toFixed(1),
            );

            if (t < 1) {
                frame = requestAnimationFrame(tick);
            } else {
                fromRef.current = to;
                setTween(null);
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [durationMs, isNumeric, numeric, reducedMotion]);

    const display =
        !isNumeric || reducedMotion || tween === null
            ? String(value)
            : tween;

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
