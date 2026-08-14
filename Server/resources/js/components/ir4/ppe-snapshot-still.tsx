import { cn } from '@/lib/utils';

type Props = {
    url: string | null;
    alt?: string;
    className?: string;
};

export function PpeSnapshotStill({ url, alt = '', className }: Props) {
    if (url === null || url === '') {
        return (
            <div
                className={cn(
                    'flex items-center justify-center rounded-[var(--radius-sm)] border border-dashed border-border bg-muted/40 text-center text-xs text-muted-foreground',
                    className,
                )}
            >
                No camera still
            </div>
        );
    }

    return (
        <img
            src={url}
            alt={alt}
            className={cn(
                'rounded-[var(--radius-sm)] object-cover',
                className,
            )}
        />
    );
}
