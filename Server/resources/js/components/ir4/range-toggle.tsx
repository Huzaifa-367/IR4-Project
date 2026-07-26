import { cn } from '@/lib/utils';

export type RangeOption<T extends string = string> = {
    value: T;
    label: string;
};

type Props<T extends string> = {
    options: RangeOption<T>[];
    value: T;
    onChange: (value: T) => void;
    className?: string;
    'aria-label'?: string;
};

export function RangeToggle<T extends string>({
    options,
    value,
    onChange,
    className,
    'aria-label': ariaLabel = 'Range',
}: Props<T>) {
    return (
        <div
            role="group"
            aria-label={ariaLabel}
            className={cn(
                'inline-flex items-center gap-0.5 rounded-pill border border-border bg-bg p-0.5',
                className,
            )}
        >
            {options.map((option) => {
                const active = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'rounded-pill px-2.5 py-1 text-[12px] font-medium transition-colors',
                            active
                                ? 'bg-surface-3 text-text'
                                : 'bg-transparent text-text-dim hover:text-text',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
