export type GasLivePanel = {
    device_id: number;
    device_name: string;
    device_ref: string;
    device_type: string;
    asset_label: string | null;
    recorded_at: string | null;
    is_stale: boolean;
    lel_pct: number | null;
    h2s_ppm: number | null;
    o2_pct: number | null;
    co_ppm: number | null;
    co2_ppm: number | null;
    open_alarms: Array<{ gas_type: string; level: string }>;
};

export type GasThreshold = {
    id?: number;
    gas_type: string;
    label?: string;
    warning_level: number;
    alarm_level: number;
    unit: string;
    direction: string;
    is_active?: boolean;
    updated_by_name?: string | null;
    updated_at?: string | null;
};

export type GasAlarm = {
    id: number;
    uuid: string;
    device_id: number;
    device_name: string | null;
    device_ref: string | null;
    gas_type: string;
    level: string;
    reading_value: number;
    threshold_value: number;
    triggered_at: string;
    resolved_at: string | null;
    acknowledged_by: number | null;
    acknowledged_by_name: string | null;
    acknowledged_at: string | null;
    during_outage: boolean;
    alert_id: number | null;
    is_open: boolean;
};

export type GasTrendSeries = {
    points: Array<{
        at: string;
        value: number | null;
        min: number | null;
        avg: number | null;
        max: number | null;
        device_id: number | null;
    }>;
    source: string;
};

export type GasDashboardSnapshot = {
    as_of: string;
    panel_health: {
        total: number;
        current: number;
        stale: number;
    };
    open_alarms: number;
    metrics: Array<{
        key: string;
        label: string;
        unit: string;
        current: number | null;
        min: number | null;
        avg: number | null;
        max: number | null;
        sparkline: number[];
    }>;
    trend: {
        source: string;
        series: Array<{
            key: string;
            label: string;
            unit: string;
            source: string;
            points: GasTrendSeries['points'];
        }>;
    };
};
