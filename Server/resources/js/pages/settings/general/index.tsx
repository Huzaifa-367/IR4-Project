import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { GasThresholdsEditor } from '@/components/ir4/settings/gas-thresholds-editor';
import { SensitiveSettingConfirm } from '@/components/ir4/settings/sensitive-setting-confirm';
import { SettingGroup } from '@/components/ir4/settings/setting-group';
import { SettingsModuleTabs } from '@/components/ir4/settings/settings-module-tabs';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import gas from '@/routes/gas';
import reports from '@/routes/reports';
import settings from '@/routes/settings';
import type { GasThreshold } from '@/types/gas';
import type {
    SettingGroup as SettingGroupType,
    SettingSchema,
} from '@/types/settings';

type SettingValue = string | number | boolean | null;
type Values = Record<string, SettingValue>;

type Props = {
    groups: SettingGroupType[];
    gasThresholds: GasThreshold[] | null;
    canUpdateGasThresholds: boolean;
};

type PendingConfirm = {
    setting: SettingSchema;
    value: string | number | boolean;
};

function flattenValues(groups: SettingGroupType[]): Values {
    const values: Values = {};

    for (const group of groups) {
        for (const setting of group.settings) {
            values[setting.key] = setting.value;
        }
    }

    return values;
}

function indexSettings(groups: SettingGroupType[]): Map<string, SettingSchema> {
    const map = new Map<string, SettingSchema>();

    for (const group of groups) {
        for (const setting of group.settings) {
            map.set(setting.key, setting);
        }
    }

    return map;
}

function dirtyKeysInGroup(group: SettingGroupType, values: Values): string[] {
    return group.settings
        .filter(
            (setting) =>
                setting.editable && values[setting.key] !== setting.value,
        )
        .map((setting) => setting.key);
}

function resolveTab(
    groups: SettingGroupType[],
    extraKeys: string[] = [],
): string {
    const fallback = groups[0]?.key ?? extraKeys[0] ?? 'general';
    const allowed = new Set([
        ...groups.map((group) => group.key),
        ...extraKeys,
    ]);

    if (typeof window === 'undefined') {
        return fallback;
    }

    const tab = new URLSearchParams(window.location.search).get('tab');

    return tab && allowed.has(tab) ? tab : fallback;
}

function syncTabInUrl(tab: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

function geolocationErrorMessage(error: GeolocationPositionError): string {
    if (error.code === error.PERMISSION_DENIED) {
        return 'Location permission denied for this site.';
    }

    if (error.code === error.TIMEOUT) {
        return 'Location request timed out. Enter latitude/longitude manually, or retry.';
    }

    // POSITION_UNAVAILABLE — common on SCC desktops: no GPS, and network
    // location needs outbound DNS/HTTPS (often blocked on-prem).
    return 'No position available (SCC has no GPS; network location needs internet DNS). Enter latitude/longitude manually.';
}

function TabPanelHeader({ label }: { label: string }): ReactNode {
    return (
        <div className="flex flex-col gap-0.5 lg:hidden">
            <p className="eyebrow">Module</p>
            <h2 className="font-display text-lg font-semibold tracking-tight text-text">
                {label}
            </h2>
        </div>
    );
}

function TabSaveBar({
    label,
    dirtyCount,
    processing,
    onDiscard,
    onSave,
}: {
    label: string;
    dirtyCount: number;
    processing: boolean;
    onDiscard: () => void;
    onSave: () => void;
}): ReactNode {
    const idle = processing || dirtyCount === 0;

    return (
        <div className="sticky top-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-border bg-surface/95 px-4 py-3 shadow-[var(--shadow-pop)] backdrop-blur">
            <p className="text-sm text-text-dim">
                {dirtyCount} unsaved change{dirtyCount === 1 ? '' : 's'} in{' '}
                {label}
            </p>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onDiscard}
                    disabled={idle}
                >
                    Discard
                </Button>
                <Button type="button" onClick={onSave} disabled={idle}>
                    Save {label}
                </Button>
            </div>
        </div>
    );
}

function GroupFooter({
    groupKey,
    canEditCoords,
    locating,
    processing,
    locationError,
    onRefreshLocation,
}: {
    groupKey: string;
    canEditCoords: boolean;
    locating: boolean;
    processing: boolean;
    locationError: string | null;
    onRefreshLocation: () => void;
}): ReactNode {
    if (groupKey === 'gas') {
        return (
            <p className="border-t border-border pt-3 text-sm text-text-dim">
                Live readings and alarms are on the{' '}
                <Link
                    href={gas.index()}
                    className="text-[color:var(--accent)] underline"
                >
                    Gas dashboard
                </Link>
                .
            </p>
        );
    }

    if (groupKey === 'reports') {
        return (
            <p className="border-t border-border pt-3 text-sm text-text-dim">
                Weekly report history lives under{' '}
                <Link
                    href={reports.index()}
                    className="text-[color:var(--accent)] underline"
                >
                    Reports
                </Link>
                .
            </p>
        );
    }

    if (groupKey !== 'general' || !canEditCoords) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-t border-border pt-3">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onRefreshLocation}
                    disabled={locating || processing}
                >
                    {locating ? 'Detecting…' : 'Refresh location'}
                </Button>
                <p className="text-xs text-text-dim">
                    Fills latitude/longitude from this browser over HTTPS, then
                    Save. On-prem SCCs often have no GPS — type coords manually
                    if detect fails.
                </p>
            </div>
            {locationError ? (
                <p className="text-xs text-[color:var(--danger)]">
                    {locationError}
                </p>
            ) : null}
        </div>
    );
}

export default function GeneralSettingsPage({
    groups,
    gasThresholds,
    canUpdateGasThresholds,
}: Props) {
    const page = usePage();
    const serverErrors = (page.props.errors ?? {}) as Record<string, string>;

    const initialValues = useMemo(() => flattenValues(groups), [groups]);
    const settingIndex = useMemo(() => indexSettings(groups), [groups]);

    const [values, setValues] = useState(initialValues);
    const [confirmedKeys, setConfirmedKeys] = useState<string[]>([]);
    const [prevInitialValues, setPrevInitialValues] = useState(initialValues);
    const [activeTab, setActiveTab] = useState(() =>
        resolveTab(groups, gasThresholds !== null ? ['gas'] : []),
    );
    const [pendingConfirm, setPendingConfirm] = useState<PendingConfirm | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);
    const [locating, setLocating] = useState(false);
    const [locationError, setLocationError] = useState<string | null>(null);

    if (initialValues !== prevInitialValues) {
        setPrevInitialValues(initialValues);
        setValues(initialValues);
        setConfirmedKeys([]);
        setLocationError(null);
    }

    const tabs = useMemo((): SettingGroupType[] => {
        if (
            gasThresholds === null ||
            groups.some((group) => group.key === 'gas')
        ) {
            return groups;
        }

        return [...groups, { key: 'gas', label: 'Gas', settings: [] }];
    }, [groups, gasThresholds]);

    const activeGroup =
        tabs.find((group) => group.key === activeTab) ?? tabs[0] ?? null;

    const dirtyByKey = useMemo(() => {
        const counts: Record<string, number> = {};

        for (const group of groups) {
            counts[group.key] = dirtyKeysInGroup(group, values).length;
        }

        return counts;
    }, [groups, values]);

    const activeDirtyKeys = activeGroup
        ? dirtyKeysInGroup(activeGroup, values)
        : [];

    const canEditCoords =
        (settingIndex.get('general.site_latitude')?.editable ?? false) &&
        (settingIndex.get('general.site_longitude')?.editable ?? false);

    const selectTab = (key: string): void => {
        setActiveTab(key);
        syncTabInUrl(key);
    };

    const applyValue = (
        key: string,
        value: string | number | boolean,
        confirmed = false,
    ): void => {
        setValues((current) => ({ ...current, [key]: value }));
        setConfirmedKeys((current) => {
            const without = current.filter((item) => item !== key);

            return confirmed ? [...without, key] : without;
        });
    };

    const handleChange = (key: string, value: string | number | boolean) => {
        const setting = settingIndex.get(key);

        if (!setting?.editable) {
            return;
        }

        if (setting.requires_confirm && value !== setting.value) {
            setPendingConfirm({ setting, value });

            return;
        }

        applyValue(key, value);
    };

    const refreshLocation = (): void => {
        if (!canEditCoords || locating) {
            return;
        }

        if (typeof window !== 'undefined' && !window.isSecureContext) {
            setLocationError(
                'Geolocation needs HTTPS (open https://ir4-project.test, not a bare IP).',
            );

            return;
        }

        if (!navigator.geolocation) {
            setLocationError('Geolocation is not available in this browser.');

            return;
        }

        setLocating(true);
        setLocationError(null);

        const applyPosition = (position: GeolocationPosition): void => {
            setValues((current) => ({
                ...current,
                'general.site_latitude': position.coords.latitude.toFixed(6),
                'general.site_longitude': position.coords.longitude.toFixed(6),
            }));
            setLocating(false);
        };

        // Prefer network/Wi‑Fi location first — SCC boxes have no GPS;
        // enableHighAccuracy often times out waiting for a fix that never comes.
        navigator.geolocation.getCurrentPosition(
            applyPosition,
            () => {
                navigator.geolocation.getCurrentPosition(
                    applyPosition,
                    (error) => {
                        setLocationError(geolocationErrorMessage(error));
                        setLocating(false);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 20000,
                        maximumAge: 0,
                    },
                );
            },
            {
                enableHighAccuracy: false,
                timeout: 20000,
                maximumAge: 60_000,
            },
        );
    };

    const discardActiveTab = (): void => {
        if (!activeGroup) {
            return;
        }

        const keys = new Set(
            activeGroup.settings.map((setting) => setting.key),
        );

        setValues((current) => {
            const next = { ...current };

            for (const setting of activeGroup.settings) {
                next[setting.key] = setting.value;
            }

            return next;
        });
        setConfirmedKeys((current) => current.filter((key) => !keys.has(key)));
        setLocationError(null);
    };

    const submitActiveTab = (): void => {
        if (activeDirtyKeys.length === 0) {
            return;
        }

        const payload: Values = {};

        for (const key of activeDirtyKeys) {
            payload[key] = values[key];
        }

        setProcessing(true);
        router.put(
            settings.general.update.url(),
            {
                settings: payload,
                confirmed: confirmedKeys.filter((key) =>
                    activeDirtyKeys.includes(key),
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
                description="Runtime tunables by module. Deploy-fixed values (DB, Reverb, printer IP) stay in .env."
            >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
                    <SettingsModuleTabs
                        groups={tabs}
                        activeKey={activeGroup?.key ?? activeTab}
                        dirtyByKey={dirtyByKey}
                        onSelect={selectTab}
                        className="shrink-0 lg:sticky lg:top-4 lg:w-52"
                    />

                    {activeGroup ? (
                        <div
                            role="tabpanel"
                            className="flex min-w-0 flex-1 flex-col gap-4"
                        >
                            <TabPanelHeader label={activeGroup.label} />
                            {activeGroup.settings.length > 0 ? (
                                <TabSaveBar
                                    label={activeGroup.label}
                                    dirtyCount={activeDirtyKeys.length}
                                    processing={processing}
                                    onDiscard={discardActiveTab}
                                    onSave={submitActiveTab}
                                />
                            ) : null}
                            {activeGroup.settings.length > 0 ? (
                                <SettingGroup
                                    group={activeGroup}
                                    values={values}
                                    errors={serverErrors}
                                    onChange={handleChange}
                                    showHeader={false}
                                    footer={
                                        <GroupFooter
                                            groupKey={activeGroup.key}
                                            canEditCoords={canEditCoords}
                                            locating={locating}
                                            processing={processing}
                                            locationError={locationError}
                                            onRefreshLocation={refreshLocation}
                                        />
                                    }
                                />
                            ) : (
                                <GroupFooter
                                    groupKey={activeGroup.key}
                                    canEditCoords={canEditCoords}
                                    locating={locating}
                                    processing={processing}
                                    locationError={locationError}
                                    onRefreshLocation={refreshLocation}
                                />
                            )}
                            {activeGroup.key === 'gas' &&
                            gasThresholds !== null ? (
                                <GasThresholdsEditor
                                    thresholds={gasThresholds}
                                    canManage={canUpdateGasThresholds}
                                />
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </SettingsPageShell>

            <SensitiveSettingConfirm
                open={pendingConfirm !== null}
                setting={pendingConfirm?.setting ?? null}
                nextValue={pendingConfirm?.value ?? null}
                onCancel={() => setPendingConfirm(null)}
                onConfirm={() => {
                    if (!pendingConfirm) {
                        return;
                    }

                    applyValue(
                        pendingConfirm.setting.key,
                        pendingConfirm.value,
                        true,
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
            href: settings.general.edit(),
        },
    ],
};
