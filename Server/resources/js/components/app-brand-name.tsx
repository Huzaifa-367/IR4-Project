import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    compact?: boolean;
};

export function AppBrandName({ className, compact = false }: Props) {
    return (
        <div className={cn('min-w-0 text-left leading-tight', className)}>
            <div
                className={cn(
                    'truncate font-bold tracking-tight text-text',
                    compact ? 'text-sm' : 'text-base',
                )}
            >
                IR4 Command
            </div>
            <div
                className={cn(
                    'truncate font-medium tracking-[0.08em] text-text-faint uppercase',
                    compact ? 'text-[9px]' : 'text-[10px]',
                )}
            >
                Safety Center
            </div>
        </div>
    );
}
