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
        <section className="space-y-4 rounded-xl border border-border bg-card p-4">
            <h2 className="text-base font-semibold tracking-tight">
                {group.label}
            </h2>
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
