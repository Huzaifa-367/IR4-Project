import type { ReactNode } from 'react';

type Props = {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
    filters?: ReactNode;
    children: ReactNode;
};

export function SettingsPageShell({
    eyebrow = 'Settings',
    title,
    description,
    actions,
    filters,
    children,
}: Props) {
    return (
        <div className="flex flex-col gap-5 p-4 md:p-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="eyebrow">{eyebrow}</p>
                    <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-[28px]">
                        {title}
                    </h1>
                    {description ? (
                        <p className="mt-1 text-sm text-text-dim">
                            {description}
                        </p>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
            {filters ? (
                <div className="flex flex-wrap items-end gap-3">{filters}</div>
            ) : null}
            {children}
        </div>
    );
}
