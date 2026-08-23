import { useEffect, useRef } from 'react';
import type { Ref } from 'react';
import { cn } from '@/lib/utils';
import type { SettingGroup as SettingGroupType } from '@/types/settings';

type Props = {
    groups: SettingGroupType[];
    activeKey: string;
    dirtyByKey: Record<string, number>;
    onSelect: (key: string) => void;
    className?: string;
};

export function SettingsModuleTabs({
    groups,
    activeKey,
    dirtyByKey,
    onSelect,
    className,
}: Props) {
    const mobileListRef = useRef<HTMLDivElement>(null);
    const activeMobileRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        activeMobileRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center',
        });
    }, [activeKey]);

    return (
        <nav
            aria-label="Settings modules"
            className={cn('flex flex-col gap-3', className)}
        >
            {/* Mobile: horizontal scroll strip */}
            <div
                ref={mobileListRef}
                className="overflow-x-auto overscroll-x-contain lg:hidden"
            >
                <div
                    role="tablist"
                    className="flex min-w-max gap-0 border-b border-border"
                >
                    {groups.map((group) => (
                        <TabButton
                            key={group.key}
                            ref={
                                group.key === activeKey
                                    ? activeMobileRef
                                    : undefined
                            }
                            label={group.label}
                            selected={group.key === activeKey}
                            dirtyCount={dirtyByKey[group.key] ?? 0}
                            variant="underline"
                            onSelect={() => onSelect(group.key)}
                        />
                    ))}
                </div>
            </div>

            {/* Desktop: vertical module list */}
            <div
                role="tablist"
                className="hidden flex-col gap-0.5 rounded-[var(--radius-sm)] border border-border bg-surface p-1 lg:flex"
            >
                {groups.map((group) => (
                    <TabButton
                        key={group.key}
                        label={group.label}
                        selected={group.key === activeKey}
                        dirtyCount={dirtyByKey[group.key] ?? 0}
                        variant="sidebar"
                        onSelect={() => onSelect(group.key)}
                    />
                ))}
            </div>
        </nav>
    );
}

type TabButtonProps = {
    label: string;
    selected: boolean;
    dirtyCount: number;
    variant: 'underline' | 'sidebar';
    onSelect: () => void;
    ref?: Ref<HTMLButtonElement>;
};

function TabButton({
    label,
    selected,
    dirtyCount,
    variant,
    onSelect,
    ref,
}: TabButtonProps) {
    const dirtyTitle =
        dirtyCount > 0
            ? `${dirtyCount} unsaved change${dirtyCount === 1 ? '' : 's'}`
            : undefined;

    if (variant === 'underline') {
        return (
            <button
                ref={ref}
                type="button"
                role="tab"
                aria-selected={selected}
                onClick={onSelect}
                title={dirtyTitle}
                className={cn(
                    'relative shrink-0 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                    selected ? 'text-text' : 'text-text-dim hover:text-text',
                )}
            >
                <span className="inline-flex items-center gap-1.5">
                    {label}
                    {dirtyCount > 0 ? <DirtyBadge count={dirtyCount} /> : null}
                </span>
                <span
                    aria-hidden
                    className={cn(
                        'absolute inset-x-3 -bottom-px h-0.5 rounded-full transition-colors',
                        selected
                            ? 'bg-[color:var(--accent)]'
                            : 'bg-transparent',
                    )}
                />
            </button>
        );
    }

    return (
        <button
            type="button"
            role="tab"
            aria-selected={selected}
            onClick={onSelect}
            title={dirtyTitle}
            className={cn(
                'flex w-full items-center justify-between gap-2 rounded-[var(--radius-sm)] px-3 py-2 text-left text-sm transition-colors',
                selected
                    ? 'bg-[color:var(--accent-dim)] font-medium text-text'
                    : 'text-text-dim hover:bg-surface-2 hover:text-text',
            )}
        >
            <span className="truncate">{label}</span>
            {dirtyCount > 0 ? <DirtyBadge count={dirtyCount} /> : null}
        </button>
    );
}

function DirtyBadge({ count }: { count: number }) {
    return (
        <span
            className="inline-flex min-w-4 shrink-0 items-center justify-center rounded-pill bg-[color:var(--accent)] px-1 py-px text-[10px] leading-none font-semibold text-[color:var(--accent-foreground)] tabular-nums"
            aria-hidden
        >
            {count}
        </span>
    );
}
