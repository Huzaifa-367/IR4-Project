export type ReportStatus = 'draft' | 'generated' | 'published';

export type CompletenessNote = {
    item: string;
    message: string;
};

export type WeeklyReportData = {
    period: { start: string; end: string };
    i_daily_safety_observations: {
        per_day: Array<{
            date: string;
            by_type: Record<string, number>;
            total: number;
        }>;
        by_camera: Array<{ camera: string; total: number }>;
        false_positives_excluded: number;
    };
    ii_hse_incidents: Array<Record<string, unknown>>;
    iii_lsr_violations: {
        summary_by_category: Array<{ category: string; count: number }>;
        entries: Array<Record<string, unknown>>;
    };
    iv_weather: { per_day: Array<Record<string, unknown>> };
    v_manpower: { per_day: Array<Record<string, unknown>> };
    vi_units_monitored: { count: number; note: string };
    vii_vehicle_violations: Array<Record<string, unknown>>;
    viii_environmental: { per_day: Array<Record<string, unknown>> };
    ix_gas: {
        per_gas_per_day: Array<Record<string, unknown>>;
        alarm_events: Array<Record<string, unknown>>;
    };
    x_co2: {
        per_day: Array<Record<string, unknown>>;
        alarm_events: Array<Record<string, unknown>>;
    };
    completeness: { notes: CompletenessNote[] };
};

export type WeeklyReport = {
    id: number;
    report_number: string;
    period_start: string;
    period_end: string;
    status: ReportStatus;
    status_label: string;
    generated_at: string | null;
    generated_by_name: string | null;
    published_at: string | null;
    published_by_name: string | null;
    has_pdf: boolean;
    has_csv: boolean;
    supersedes_report_id: number | null;
    supersedes_report_number: string | null;
    superseded_by_report_numbers: string[];
    data: WeeklyReportData;
    created_at: string | null;
};

export type VehicleViolation = {
    id: number;
    observed_at: string | null;
    vehicle_description: string;
    violation_type: string;
    description: string | null;
    action_taken: string;
    camera_id: number | null;
    camera_name: string | null;
    logged_by_name: string | null;
    created_at: string | null;
};

export type ReportSettings = {
    generation_day: string;
    generation_time: string;
    auto_publish: boolean;
    completeness_threshold_pct: number;
};
