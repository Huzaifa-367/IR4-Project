import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { SensitiveSettingConfirm } from '@/components/ir4/settings/sensitive-setting-confirm';
import { SettingGroup } from '@/components/ir4/settings/setting-group';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import type {
    SettingGroup as SettingGroupType,
    SettingSchema,
} from '@/types/settings';

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
    const [prevInitialValues, setPrevInitialValues] = useState(initialValues);
    const [pendingConfirm, setPendingConfirm] = useState<{
        setting: SettingSchema;
        value: string | number | boolean;
    } | null>(null);
    const [processing, setProcessing] = useState(false);

    if (initialValues !== prevInitialValues) {
        setPrevInitialValues(initialValues);
        setValues(initialValues);
        setConfirmedKeys([]);
    }

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

    const discard = (): void => {
        setValues(initialValues);
        setConfirmedKeys([]);
    };

    const submit = (): void => {
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
            <SettingsPageShell
                title="General settings"
                description="Runtime tunables. Deploy-fixed values (DB, Reverb, printer IP) stay in .env."
            >
                <div className="grid gap-4 xl:grid-cols-2">
                    {groups.map((group) => (
                        <SettingGroup
                            key={group.key}
                            group={group}
                            values={values}
                            errors={serverErrors}
                            onChange={handleChange}
                            footer={
                                group.key === 'gas' ? (
                                    <p className="border-t border-border pt-3 text-sm text-text-dim">
                                        Gas alarm thresholds are managed in the{' '}
                                        <Link
                                            href={gasThresholdsUrl}
                                            className="text-[color:var(--accent)] underline"
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

                <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-border bg-surface/95 px-4 py-3 shadow-[var(--shadow-pop)] backdrop-blur">
                    <p className="text-sm text-text-dim">
                        {dirtyKeys.length} unsaved change
                        {dirtyKeys.length === 1 ? '' : 's'}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={discard}
                            disabled={processing || dirtyKeys.length === 0}
                        >
                            Discard
                        </Button>
                        <Button
                            type="button"
                            onClick={submit}
                            disabled={processing || dirtyKeys.length === 0}
                        >
                            Save changes
                        </Button>
                    </div>
                </div>
            </SettingsPageShell>

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
