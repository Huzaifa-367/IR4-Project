import { cn } from '@/lib/utils';

function Empty({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="empty"
            className={cn(
                'flex min-h-40 flex-col items-center justify-center gap-2 rounded-[var(--radius-sm)] border border-dashed border-border px-6 py-10 text-center',
                className,
            )}
            {...props}
        />
    );
}

function EmptyTitle({ className, ...props }: React.ComponentProps<'h3'>) {
    return (
        <h3
            data-slot="empty-title"
            className={cn('text-sm font-semibold text-text', className)}
            {...props}
        />
    );
}

function EmptyDescription({ className, ...props }: React.ComponentProps<'p'>) {
    return (
        <p
            data-slot="empty-description"
            className={cn('text-sm text-text-dim', className)}
            {...props}
        />
    );
}

export { Empty, EmptyDescription, EmptyTitle };
