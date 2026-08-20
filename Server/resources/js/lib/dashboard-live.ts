import type { DashboardSummary } from '@/types/dashboard';
import { systemHealthAssets } from '@/types/dashboard';

type AlertPayload = {
    id: number;
    uuid: string;
    title: string;
    severity: string;
    status: string;
    raised_at: string;
    alert_type_label?: string;
    payload?: Record<string, unknown>;
};

type HeadcountPayload = {
    total_on_site: number;
    by_zone: Array<{ zone_id: number; count: number; zone_name: string }>;
};

type PositionDelta = {
    tag_id: number;
    worker_id?: number | null;
    zone_id?: number | null;
    last_seen_at?: string;
    is_on_site?: boolean;
};

type DeviceStatusPayload = {
    device_id: number;
    status: string;
    device_type?: string;
    device_name: string;
    asset_id?: number | null;
};

type GasPanelPayload = {
    device_id: number;
    device_name?: string;
    asset_label?: string | null;
    is_stale?: boolean;
    lel_pct?: number | null;
    h2s_ppm?: number | null;
    o2_pct?: number | null;
    co_ppm?: number | null;
    co2_ppm?: number | null;
    open_alarms?: Array<{ level?: string }>;
};

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function asAlert(value: unknown): AlertPayload | null {
    if (!isRecord(value) || typeof value.id !== 'number') {
        return null;
    }

    return value as AlertPayload;
}

function applyAlert(
    summary: DashboardSummary,
    alert: AlertPayload,
): DashboardSummary {
    if (!summary.alerts) {
        return summary;
    }

    const previous = summary.alerts.latest.find((row) => row.id === alert.id);
    const latest = [
        alert,
        ...summary.alerts.latest.filter((row) => row.id !== alert.id),
    ].slice(0, 12);

    let openCritical = summary.alerts.open_critical;
    let openWarning = summary.alerts.open_warning;
    const wasOpen =
        previous === undefined ||
        previous.status === 'open' ||
        previous.status === 'acknowledged';
    const isOpen = alert.status === 'open' || alert.status === 'acknowledged';
    const wasCrit = previous?.severity === 'critical';
    const isCrit = alert.severity === 'critical';

    if (previous === undefined && isOpen) {
        if (isCrit) {
            openCritical += 1;
        } else {
            openWarning += 1;
        }
    } else if (previous !== undefined && wasOpen && !isOpen) {
        if (wasCrit) {
            openCritical = Math.max(0, openCritical - 1);
        } else {
            openWarning = Math.max(0, openWarning - 1);
        }
    } else if (
        previous !== undefined &&
        wasOpen &&
        isOpen &&
        wasCrit !== isCrit
    ) {
        if (isCrit) {
            openCritical += 1;
            openWarning = Math.max(0, openWarning - 1);
        } else {
            openWarning += 1;
            openCritical = Math.max(0, openCritical - 1);
        }
    }

    return {
        ...summary,
        alerts: {
            ...summary.alerts,
            open_critical: openCritical,
            open_warning: openWarning,
            latest: isOpen
                ? latest
                : latest.filter((row) => row.id !== alert.id),
        },
        meta: summary.meta
            ? { ...summary.meta, as_of: new Date().toISOString() }
            : summary.meta,
    };
}

function applyHeadcount(
    summary: DashboardSummary,
    payload: HeadcountPayload,
): DashboardSummary {
    if (!summary.headcount) {
        return summary;
    }

    return {
        ...summary,
        headcount: {
            ...summary.headcount,
            total_on_site: payload.total_on_site,
            by_zone: payload.by_zone,
        },
        meta: summary.meta
            ? { ...summary.meta, as_of: new Date().toISOString() }
            : summary.meta,
    };
}

function applyPositions(
    summary: DashboardSummary,
    deltas: PositionDelta[],
): DashboardSummary {
    if (!summary.occupancy) {
        return summary;
    }

    const byTag = new Map(
        summary.occupancy.positions.map((row) => [row.tag_id, row]),
    );

    for (const delta of deltas) {
        if (delta.is_on_site === false) {
            byTag.delete(delta.tag_id);
            continue;
        }

        const existing = byTag.get(delta.tag_id);
        const workerId = delta.worker_id ?? existing?.worker_id ?? 0;

        byTag.set(delta.tag_id, {
            tag_id: delta.tag_id,
            worker_id: workerId,
            worker_label:
                existing?.worker_label ??
                (workerId > 0 ? `Worker #${String(workerId)}` : 'Worker'),
            zone_id: delta.zone_id ?? existing?.zone_id ?? null,
            last_seen_at:
                delta.last_seen_at ??
                existing?.last_seen_at ??
                new Date().toISOString(),
            is_on_site: true,
        });
    }

    const positions = [...byTag.values()];
    const redZoneIds = new Set(
        summary.occupancy.zones
            .filter((zone) => zone.zone_type === 'restricted_red')
            .map((zone) => zone.id),
    );

    return {
        ...summary,
        occupancy: {
            ...summary.occupancy,
            positions,
            in_red: positions.filter(
                (row) => row.zone_id !== null && redZoneIds.has(row.zone_id),
            ).length,
        },
        meta: summary.meta
            ? { ...summary.meta, as_of: new Date().toISOString() }
            : summary.meta,
    };
}

function applyGasPanel(
    summary: DashboardSummary,
    panel: GasPanelPayload,
): DashboardSummary {
    if (!summary.gas) {
        return summary;
    }

    const openAlarms = panel.open_alarms ?? [];
    const isStale = Boolean(panel.is_stale);
    let status: 'ok' | 'warn' | 'crit' = 'ok';

    if (isStale || openAlarms.length > 0) {
        const hasCritical = openAlarms.some(
            (alarm) => alarm.level === 'alarm' || alarm.level === 'critical',
        );
        status = hasCritical || isStale ? 'crit' : 'warn';
    }

    const mapped = {
        device_id: panel.device_id,
        asset: panel.asset_label ?? panel.device_name ?? null,
        device_name: panel.device_name,
        status,
        channels: {
            lel_pct: panel.lel_pct ?? null,
            h2s_ppm: panel.h2s_ppm ?? null,
            o2_pct: panel.o2_pct ?? null,
            co_ppm: panel.co_ppm ?? null,
            co2_ppm: panel.co2_ppm ?? null,
        },
        stale: isStale,
    };

    const panels = summary.gas.panels.some(
        (row) => row.device_id === panel.device_id,
    )
        ? summary.gas.panels.map((row) =>
              row.device_id === panel.device_id ? mapped : row,
          )
        : [...summary.gas.panels, mapped];

    return {
        ...summary,
        gas: {
            ...summary.gas,
            panels,
        },
        meta: summary.meta
            ? { ...summary.meta, as_of: new Date().toISOString() }
            : summary.meta,
    };
}

export function patchSystemHealth(
    health: DashboardSummary['system_health'],
    payload: DeviceStatusPayload,
): DashboardSummary['system_health'] {
    const assets = systemHealthAssets(health);
    const offline = payload.status === 'offline' || payload.status === 'fault';

    const nextAssets = assets.map((asset) => {
        const matchesAsset =
            payload.asset_id != null
                ? asset.asset_id === payload.asset_id
                : asset.offline_components.includes(payload.device_name);

        if (!matchesAsset) {
            return asset;
        }

        const components = offline
            ? asset.offline_components.includes(payload.device_name)
                ? asset.offline_components
                : [...asset.offline_components, payload.device_name]
            : asset.offline_components.filter(
                  (name) => name !== payload.device_name,
              );
        const status: 'green' | 'amber' | 'red' =
            components.length === 0
                ? 'green'
                : components.length >= 2
                  ? 'red'
                  : 'amber';

        return {
            ...asset,
            offline_components: components,
            status,
        };
    });

    if (!health || Array.isArray(health)) {
        return nextAssets;
    }

    // online/total are device counts (not poles). Skip camera events;
    // DeviceStatusChanged for devices adjusts the running totals until poll.
    let online = health.online;
    const total = health.total;

    if (payload.device_type !== 'camera') {
        const becameOffline =
            payload.status === 'offline' || payload.status === 'fault';

        if (becameOffline) {
            online = Math.max(0, online - 1);
        } else if (payload.status === 'online') {
            online = Math.min(total, online + 1);
        }
    }

    return {
        ...health,
        assets: nextAssets,
        online,
        total,
        uptime_pct: total > 0 ? Math.round((online / total) * 1000) / 10 : 100,
    };
}

function applyDeviceStatus(
    summary: DashboardSummary,
    payload: DeviceStatusPayload,
): DashboardSummary {
    if (!summary.system_health) {
        return summary;
    }

    return {
        ...summary,
        system_health: patchSystemHealth(summary.system_health, payload),
        meta: summary.meta
            ? { ...summary.meta, as_of: new Date().toISOString() }
            : summary.meta,
    };
}

export function applyDashboardEvent(
    summary: DashboardSummary,
    payload: unknown,
): DashboardSummary {
    if (!isRecord(payload)) {
        return summary;
    }

    if ('alert' in payload) {
        const alert = asAlert(payload.alert);

        return alert === null ? summary : applyAlert(summary, alert);
    }

    if ('total_on_site' in payload && 'by_zone' in payload) {
        return applyHeadcount(summary, payload as unknown as HeadcountPayload);
    }

    if ('positions' in payload && Array.isArray(payload.positions)) {
        return applyPositions(summary, payload.positions as PositionDelta[]);
    }

    if ('panel' in payload && isRecord(payload.panel)) {
        return applyGasPanel(
            summary,
            payload.panel as unknown as GasPanelPayload,
        );
    }

    if (
        'device_id' in payload &&
        'status' in payload &&
        'device_name' in payload
    ) {
        return applyDeviceStatus(
            summary,
            payload as unknown as DeviceStatusPayload,
        );
    }

    return summary;
}
