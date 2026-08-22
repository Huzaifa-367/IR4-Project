export type EnvironmentSensor = {
    device_id: number;
    device_name: string;
    device_ref: string;
    device_status?: string;
    asset_label: string | null;
    recorded_at: string | null;
    last_seen_at?: string | null;
    is_online?: boolean;
    is_stale: boolean;
    temperature_c: number | null;
    humidity_pct: number | null;
    wind_speed_ms: number | null;
    extra: Record<string, number>;
    weather_source?: string;
};

export type EnvironmentTrendPoint = {
    at: string;
    value: number | null;
    min: number | null;
    avg: number | null;
    max: number | null;
    device_id: number | null;
};

export type EnvironmentMetricTrend = {
    key: string;
    label: string;
    unit: string;
    points: EnvironmentTrendPoint[];
};

export type EnvironmentCoreTrends = {
    source: 'raw' | 'raw-hourly';
    series: EnvironmentMetricTrend[];
};
