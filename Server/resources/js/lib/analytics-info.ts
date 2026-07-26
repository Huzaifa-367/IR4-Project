import type { SectionInfoContent } from '@/components/ir4/section-info';

export const gasInfo = {
    lel: {
        summary: 'Lower Explosive Limit average across live detectors.',
        items: [
            'Current value is the live site average.',
            'Sparkline / range stats use the selected 24h, 7d, or custom window.',
        ],
        source: 'gas_readings · lel_pct',
    },
    h2s: {
        summary: 'Hydrogen sulphide average across live detectors.',
        items: [
            'Compared against DOC-11 warning and alarm thresholds on live panels.',
            'Trend points use raw readings (hourly aggregates for windows longer than 24 hours).',
        ],
        source: 'gas_readings · h2s_ppm',
    },
    o2: {
        summary: 'Oxygen volume average across live detectors.',
        items: [
            'Uses both O₂-low and O₂-high threshold rules for panel status.',
            'Range average is site-wide across the selected window.',
        ],
        source: 'gas_readings · o2_pct',
    },
    co: {
        summary: 'Carbon monoxide average across live detectors.',
        items: [
            'Current value is the live site (or device) average.',
            'Range max and sparkline use the selected window.',
        ],
        source: 'gas_readings · co_ppm',
    },
    co2: {
        summary: 'Carbon dioxide average across live detectors.',
        items: [
            'Current value is the live site (or device) average.',
            'Range max and sparkline use the selected window.',
        ],
        source: 'gas_readings · co2_ppm',
    },
    health: {
        summary: 'How many gas / CO₂ detectors are reporting fresh data.',
        items: [
            'Stale means last reading is older than health.gas_stale_minutes.',
            'Live count — not filtered by the chart date range.',
        ],
        source: 'devices · latest gas_readings',
    },
    trend: {
        summary: 'All gas channels plotted together for the selected range.',
        items: [
            'Series: LEL, H₂S, O₂, CO, CO₂.',
            'Device filter scopes every channel to one detector.',
            'Falls back to hourly SQL aggregation over raw readings for windows longer than 24 hours.',
        ],
        source: 'gas_readings',
    },
    rangeStats: {
        summary: 'Min / average / max for each gas channel in the selected window.',
        items: [
            'Computed from the same trend series as the chart.',
            'Current column is the live average from detector panels.',
        ],
        source: 'dashboardSnapshot metrics',
    },
    fleet: {
        summary: 'Per-detector live gauges versus active thresholds.',
        items: [
            'Colour bands follow warning_level and alarm_level.',
            'Open alarms listed under each panel when present.',
        ],
        source: 'livePanels · gas_thresholds',
    },
} as const satisfies Record<string, SectionInfoContent>;

export const environmentInfo = {
    temperature: {
        summary: 'Ambient temperature from environmental sensors.',
        items: [
            'Current is the live average across sensors.',
            'Min–max and sparkline use the selected range.',
        ],
        source: 'environmental_readings · temperature_c',
    },
    humidity: {
        summary: 'Relative humidity from environmental sensors.',
        items: [
            'Display-only in v1 — no environmental alarms.',
            'Range average uses the selected 24h / 7d / custom window.',
        ],
        source: 'environmental_readings · humidity_pct',
    },
    wind: {
        summary: 'Wind speed from environmental sensors.',
        items: [
            'Current is the live average.',
            'Range max comes from the selected window trend.',
        ],
        source: 'environmental_readings · wind_speed_ms',
    },
    sensorHealth: {
        summary: 'How many environmental sensors are reporting fresh data.',
        items: [
            'Stale uses health.sensor_stale_minutes.',
            'Live fleet status — not range-filtered.',
        ],
        source: 'devices · latest environmental_readings',
    },
    trend: {
        summary: 'Temperature, humidity, wind, and extra metrics on one chart.',
        items: [
            'Uses raw readings within 24 hours.',
            'Uses hourly aggregates from raw readings for longer windows.',
        ],
        source: 'environmental_readings',
    },
    rangeStats: {
        summary: 'Min / average / max for each core environmental metric.',
        items: ['Derived from the chart series for the selected window.'],
        source: 'dashboardSnapshot metrics',
    },
    extra: {
        summary: 'Dynamically reported extra parameters such as PM2.5.',
        items: [
            'Keys come from the open `extra` JSON on readings.',
            'Shown only when at least one sensor reports the key.',
        ],
        source: 'environmental_readings.extra',
    },
    fleet: {
        summary: 'Latest reading card per environmental sensor.',
        items: [
            'Updates live on the environment Reverb channel.',
            'Shows temperature, humidity, and wind for each device.',
        ],
        source: 'EnvironmentalDataService::latest',
    },
} as const satisfies Record<string, SectionInfoContent>;

export const ppeInfo = {
    total: {
        summary: 'Confirmed PPE violations in the selected range.',
        items: [
            'False positives are excluded from the total.',
            'Sparkline follows violation counts across the window.',
        ],
        source: 'ppe_violations · review_status ≠ false_positive',
    },
    unreviewed: {
        summary: 'Unreviewed PPE events inside the selected range.',
        items: [
            'Delta text also shows the site-wide open unreviewed count.',
            'Reviewers confirm or mark false positive on the Violations page.',
        ],
        source: 'ppe_violations · review_status = unreviewed',
    },
    falsePositive: {
        summary: 'Share of detections marked false positive in this range.',
        items: [
            'Rate = false positives ÷ all detections in the window.',
            'Confirmed and unreviewed remain in the main totals.',
        ],
        source: 'ppe_violations review_status',
    },
    trend: {
        summary: 'Violation counts over time for the selected range.',
        items: [
            'Hourly buckets for 24h; daily buckets for longer windows.',
            'False positives are excluded.',
        ],
        source: 'PpeViolationService::dashboardSnapshot',
    },
    rangeStats: {
        summary: 'Bucket min / average / max for the violation trend.',
        items: ['Uses the same buckets as the trend chart.'],
        source: 'dashboardSnapshot metrics',
    },
    byType: {
        summary: 'Confirmed violations grouped by PPE type.',
        items: ['Helmet, vest, harness, mask totals for the window.'],
        source: 'ppe_violations group by violation_type',
    },
    byCamera: {
        summary: 'Confirmed violations grouped by camera.',
        items: ['Useful for spotting hotspots on the live wall.'],
        source: 'ppe_violations group by camera_id',
    },
} as const satisfies Record<string, SectionInfoContent>;

export const trackingInfo = {
    onSite: {
        summary: 'Workers currently marked on site from RFID presence.',
        items: [
            'Live total — not a historical range total.',
            'Updates when entry/exit or zone presence changes.',
        ],
        source: 'TrackingService headcount · worker positions',
    },
    zone: {
        summary: 'Workers currently present in this zone.',
        items: [
            'Live RFID occupancy for the named zone.',
            'Click the map zone to open zone settings.',
        ],
        source: 'headcount.by_zone',
    },
} as const satisfies Record<string, SectionInfoContent>;
