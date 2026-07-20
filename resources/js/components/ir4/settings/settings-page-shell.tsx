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
        <div className="flex flex-col gap-3 p-4 md:p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="eyebrow">{eyebrow}</p>
                    <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                        {title}
                    </h1>
                    {description ? (
                        <p className="mt-0.5 text-xs text-text-dim">
                            {description}
                        </p>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex flex-wrap items-center gap-1.5 [&_button]:h-8 [&_button]:px-3 [&_button]:text-xs">
                        {actions}
                    </div>
                ) : null}
            </div>
            {filters ? (
                <div className="flex flex-wrap items-center gap-2 [&_button]:h-8 [&_button]:px-3 [&_button]:text-xs [&_input]:h-8 [&_input]:text-xs [&_[role=combobox]]:h-8 [&_[role=combobox]]:text-xs [&_[data-slot=select-trigger]]:h-8 [&_[data-slot=select-trigger]]:text-xs">
                    {filters}
                </div>
            ) : null}
            {children}
        </div>
    );
}
