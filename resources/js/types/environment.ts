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

export type EnvironmentTrendSeries = {
    points: Array<{
        at: string;
        value: number | null;
        min: number | null;
        avg: number | null;
        max: number | null;
        device_id: number;
    }>;
    source: 'raw' | 'rollup';
};
