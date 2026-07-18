export type DashboardPermissions = {
    view_tracking: boolean;
    view_gas: boolean;
    view_ppe: boolean;
    view_incidents: boolean;
    view_lsr: boolean;
    view_equipment: boolean;
    view_reports: boolean;
};

export type DashboardSummary = {
    headcount?: {
        total_on_site: number;
        by_zone: Array<{ zone_id: number; zone_name: string; count: number }>;
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
        }>;
    };
    gas?: {
        panels: Array<{
            device_id: number;
            asset: string | null;
            status: 'ok' | 'warn' | 'crit';
            channels: Record<string, number | null>;
            co2_ppm: number | null;
            stale: boolean;
        }>;
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
    };
    incidents?: { open: number; under_review: number };
    lsr?: {
        open: number;
        by_category: Array<{ category: string; label: string; open: number }>;
    };
    equipment?: { overdue: number; due_soon: number; checked_out: number };
    system_health?: Array<{
        asset: string;
        asset_id: number;
        status: 'green' | 'amber' | 'red';
        offline_components: string[];
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
    };
};
