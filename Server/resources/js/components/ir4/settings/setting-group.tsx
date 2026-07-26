import type { ReactNode } from 'react';
import { SettingField } from '@/components/ir4/settings/setting-field';
import type { SettingGroup as SettingGroupType } from '@/types/settings';

type Props = {
    group: SettingGroupType;
    values: Record<string, string | number | boolean | null>;
    errors: Record<string, string>;
    onChange: (key: string, value: string | number | boolean) => void;
    footer?: ReactNode;
};

export function SettingGroup({
    group,
    values,
    errors,
    onChange,
    footer,
}: Props) {
    return (
        <section className="flex flex-col gap-4 rounded-[var(--radius-sm)] border border-border bg-surface p-5 shadow-[var(--shadow-card)]">
            <div>
                <p className="eyebrow">{group.key}</p>
                <h2 className="font-display text-base font-semibold tracking-tight text-text">
                    {group.label}
                </h2>
            </div>
            <div className="grid gap-5">
                {group.settings.map((setting) => (
                    <SettingField
                        key={setting.key}
                        setting={setting}
                        value={
                            values[setting.key] !== undefined
                                ? values[setting.key]
                                : setting.value
                        }
                        error={errors[setting.key]}
                        onChange={(value) => onChange(setting.key, value)}
                    />
                ))}
            </div>
            {footer}
        </section>
    );
}
