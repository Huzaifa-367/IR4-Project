export type EnvironmentSensor = {
    device_id: number;
    device_name: string;
    device_ref: string;
    asset_label: string | null;
    recorded_at: string | null;
    is_stale: boolean;
    temperature_c: number | null;
    humidity_pct: number | null;
    wind_speed_ms: number | null;
    extra: Record<string, number>;
};

export type EnvironmentTrendPoint = {
    at: string;
    value: number | null;
    min: number | null;
    avg: number | null;
    max: number | null;
    device_id: number | null;
};

export type EnvironmentTrendSeries = {
    points: EnvironmentTrendPoint[];
    source: 'raw' | 'rollup';
};

export type EnvironmentMetricTrend = {
    key: string;
    label: string;
    unit: string;
    source: 'raw' | 'rollup';
    points: EnvironmentTrendPoint[];
};

export type EnvironmentDashboardSnapshot = {
    as_of: string;
    sensors: EnvironmentSensor[];
    sensor_health: {
        total: number;
        current: number;
        stale: number;
    };
    metrics: Array<{
        key: 'temperature_c' | 'humidity_pct' | 'wind_speed_ms';
        label: string;
        unit: string;
        current: number | null;
        min: number | null;
        avg: number | null;
        max: number | null;
        sparkline: number[];
    }>;
    extra_metrics: Array<{
        key: string;
        label: string;
        current: number;
        sensor_count: number;
    }>;
    trend: {
        source: 'raw' | 'rollup';
        series: EnvironmentMetricTrend[];
    };
};
