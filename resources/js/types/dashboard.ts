export type DashboardPermissions = {
    view_tracking: boolean;
    view_gas: boolean;
    view_ppe: boolean;
    view_incidents: boolean;
    view_lsr: boolean;
    view_equipment: boolean;
    view_reports: boolean;
    trigger_evacuation?: boolean;
};

export type GasRange = 'shift' | 'day' | 'week';

export type DashboardSummary = {
    meta?: {
        shift_start: string;
        shift_end: string;
        shift_label: string;
        as_of: string;
    };
    headcount?: {
        total_on_site: number;
        by_zone: Array<{ zone_id: number; zone_name: string; count: number }>;
        shift_start_count?: number;
        delta_vs_shift_start?: number;
        peak?: number;
        sparkline?: number[];
        flow?: Array<{
            at: string;
            label: string;
            on_site: number;
            entries: number;
            exits: number;
        }>;
    };
    alerts?: {
        open_critical: number;
        open_warning: number;
        latest: Array<{
            id: number;
            title: string;
            severity: string;
            status: string;
            raised_at: string;
            alert_type_label?: string;
            payload?: Record<string, unknown>;
        }>;
        sparkline?: number[];
    };
    gas?: {
        panels: Array<{
            device_id: number;
            asset: string | null;
            device_name?: string;
            status: 'ok' | 'warn' | 'crit';
            channels: Record<string, number | null>;
            co2_ppm: number | null;
            stale: boolean;
        }>;
        channel_gauges?: Array<{
            label: string;
            source: string;
            value: number;
            unit: string;
            warn: number | null;
            alarm: number | null;
            status: 'ok' | 'warn' | 'crit';
        }>;
        thresholds?: { h2s_warn: number | null; h2s_alarm: number | null };
        trend?: {
            range: GasRange;
            labels: Array<Record<string, string | number | null>>;
            series: Array<{
                key: string;
                label: string;
                device_id: number;
                color?: string;
                points: Array<{ at: string; value: number | null }>;
                latest: number | null;
            }>;
            warn: number;
            alarm: number;
        };
    };
    weather?: {
        temperature_c: number | null;
        humidity_pct: number | null;
        wind_speed_ms: number | null;
        updated_at: string | null;
        stale: boolean;
    };
    ppe_today?: {
        total: number;
        by_type: Record<string, number>;
        trend_delta: number;
        compliance_pct?: number;
        compliance_delta?: number;
        sparkline?: number[];
        heatmap?: {
            types: Array<{ key: string; label: string }>;
            hours: number[];
            cells: number[][];
        };
    };
    incidents?: { open: number; under_review: number };
    lsr?: {
        open: number;
        by_category: Array<{
            category: string;
            label: string;
            open: number;
            total?: number;
        }>;
    };
    equipment?: { overdue: number; due_soon: number; checked_out: number };
    system_health?:
        | Array<{
              asset: string;
              asset_id: number;
              status: 'green' | 'amber' | 'red';
              offline_components: string[];
          }>
        | {
              assets: Array<{
                  asset: string;
                  asset_id: number;
                  status: 'green' | 'amber' | 'red';
                  offline_components: string[];
              }>;
              online: number;
              total: number;
              uptime_pct: number;
              sparkline?: number[];
          };
    safety_score?: {
        score: number;
        components: { ppe: number; zone: number; equipment: number };
        ppe_today: number;
        open_lsr: number;
        overdue_equipment: number;
    };
    evacuation?: {
        report_id: number | null;
        status: string | null;
        total: number;
        accounted: number;
        muster_reader: number;
        gate_exit: number;
        manual: number;
        accounted_pct: number;
        triggered_at: string | null;
    };
    open_records?: Array<{
        id: number;
        record: string;
        type: string;
        kind: 'incident' | 'lsr';
        severity: string;
        severity_label: string;
        zone: string;
        owner: string;
        owner_initials: string;
        status: string;
        status_label: string;
        action_progress: number;
        age: string;
        href: string;
        occurred_at: string | null;
    }>;
    last_report?: {
        id: number;
        report_number: string;
        period: { start: string | null; end: string | null };
        status: string;
        generated_at: string | null;
    } | null;
    map?: {
        zones: Array<{
            id: number;
            name: string;
            zone_type: string;
            map_x: number | null;
            map_y: number | null;
            map_radius: number | null;
            color: string | null;
        }>;
        positions: Array<{
            tag_id: number;
            worker_id: number;
            worker_label: string;
            zone_id: number | null;
            last_seen_at: string | null;
            is_on_site: boolean;
        }>;
        in_red?: number;
        zone_count?: number;
    };
};

export function systemHealthAssets(
    health: DashboardSummary['system_health'],
): Array<{
    asset: string;
    asset_id: number;
    status: 'green' | 'amber' | 'red';
    offline_components: string[];
}> {
    if (!health) {
        return [];
    }

    return Array.isArray(health) ? health : health.assets;
}
