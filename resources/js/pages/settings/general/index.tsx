import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { SensitiveSettingConfirm } from '@/components/ir4/settings/sensitive-setting-confirm';
import { SettingGroup } from '@/components/ir4/settings/setting-group';
import { Button } from '@/components/ui/button';
import type { SettingGroup as SettingGroupType, SettingSchema } from '@/types/settings';

type Props = {
    groups: SettingGroupType[];
    gasThresholdsUrl: string;
};

export default function GeneralSettingsPage({
    groups,
    gasThresholdsUrl,
}: Props) {
    const page = usePage();
    const serverErrors = (page.props.errors ?? {}) as Record<string, string>;

    const initialValues = useMemo(() => {
        const values: Record<string, string | number | boolean | null> = {};

        for (const group of groups) {
            for (const setting of group.settings) {
                values[setting.key] = setting.value;
            }
        }

        return values;
    }, [groups]);

    const [values, setValues] = useState(initialValues);
    const [confirmedKeys, setConfirmedKeys] = useState<string[]>([]);
    const [pendingConfirm, setPendingConfirm] = useState<{
        setting: SettingSchema;
        value: string | number | boolean;
    } | null>(null);
    const [processing, setProcessing] = useState(false);

    const settingIndex = useMemo(() => {
        const map = new Map<string, SettingSchema>();

        for (const group of groups) {
            for (const setting of group.settings) {
                map.set(setting.key, setting);
            }
        }

        return map;
    }, [groups]);

    const dirtyKeys = Object.keys(values).filter((key) => {
        const setting = settingIndex.get(key);

        if (!setting || !setting.editable) {
            return false;
        }

        return values[key] !== setting.value;
    });

    const handleChange = (key: string, value: string | number | boolean) => {
        const setting = settingIndex.get(key);

        if (!setting || !setting.editable) {
            return;
        }

        if (setting.requires_confirm && value !== setting.value) {
            setPendingConfirm({ setting, value });

            return;
        }

        setValues((current) => ({ ...current, [key]: value }));
        setConfirmedKeys((current) => current.filter((item) => item !== key));
    };

    const submit = () => {
        if (dirtyKeys.length === 0) {
            return;
        }

        const payloadSettings: Record<string, string | number | boolean | null> =
            {};

        for (const key of dirtyKeys) {
            payloadSettings[key] = values[key];
        }

        setProcessing(true);
        router.put(
            '/settings/general',
            {
                settings: payloadSettings,
                confirmed: confirmedKeys.filter((key) =>
                    dirtyKeys.includes(key),
                ),
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="General settings" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="General settings"
                    description="Runtime tunables. Deploy-fixed values (DB, Reverb, printer IP) stay in .env."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={processing || dirtyKeys.length === 0}
                    >
                        Save changes
                    </Button>
                    <p className="text-muted-foreground text-xs">
                        {dirtyKeys.length} unsaved change
                        {dirtyKeys.length === 1 ? '' : 's'}
                    </p>
                </div>

                <div className="space-y-4">
                    {groups.map((group) => (
                        <SettingGroup
                            key={group.key}
                            group={group}
                            values={values}
                            errors={serverErrors}
                            onChange={handleChange}
                            footer={
                                group.key === 'gas' ? (
                                    <p className="text-muted-foreground border-t border-border pt-3 text-sm">
                                        Gas alarm thresholds are managed in the{' '}
                                        <Link
                                            href={gasThresholdsUrl}
                                            className="text-primary underline"
                                        >
                                            gas thresholds editor
                                        </Link>
                                        .
                                    </p>
                                ) : null
                            }
                        />
                    ))}
                </div>
            </div>

            <SensitiveSettingConfirm
                open={pendingConfirm !== null}
                setting={pendingConfirm?.setting ?? null}
                nextValue={pendingConfirm?.value ?? null}
                onCancel={() => setPendingConfirm(null)}
                onConfirm={() => {
                    if (pendingConfirm === null) {
                        return;
                    }

                    setValues((current) => ({
                        ...current,
                        [pendingConfirm.setting.key]: pendingConfirm.value,
                    }));
                    setConfirmedKeys((current) =>
                        current.includes(pendingConfirm.setting.key)
                            ? current
                            : [...current, pendingConfirm.setting.key],
                    );
                    setPendingConfirm(null);
                }}
            />
        </>
    );
}

GeneralSettingsPage.layout = {
    breadcrumbs: [
        {
            title: 'General settings',
            href: '/settings/general',
        },
    ],
};
