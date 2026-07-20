import type { SectionInfoContent } from '@/components/ir4/section-info';

/** Operator-facing explanations for dashboard panels and KPI cards. */
export const dashboardInfo = {
    manpower: {
        summary:
            'Live count of workers currently marked on site from RFID entry/exit and zone presence.',
        items: [
            'Total is the current on-site headcount (not filtered by the date range).',
            'Delta compares that live total to headcount at the start of the selected range.',
            'Sparkline follows gate flow over the selected window.',
        ],
        source: 'RFID entry/exit logs · worker positions',
    },
    alerts: {
        summary:
            'Open safety alerts that still need attention in the Control Room.',
        items: [
            'Critical and warning counts are live open alerts (not range-filtered).',
            'Sparkline shows critical alerts raised over the last 12 hours.',
            'The feed lists the newest open/acknowledged alerts first.',
        ],
        source: 'alerts table · Reverb live updates',
    },
    ppeCompliance: {
        summary:
            'Estimated PPE compliance for the selected range, based on confirmed violations.',
        items: [
            'False-positive reviews are excluded from the rate.',
            'Compliance uses anonymous worker_count totals, never worker identity.',
            'Delta compares this window to the previous period of the same length.',
        ],
        source: 'ppe_violations · review_status ≠ false_positive',
    },
    systemHealth: {
        summary:
            'Hardware fleet health for cameras, gas detectors, and other registered assets.',
        items: [
            'Online % is assets currently reporting healthy status.',
            'Based on device heartbeats and last-seen telemetry.',
            'Live indicator — not filtered by the dashboard date range.',
        ],
        source: 'asset health snapshot · device heartbeats',
    },
    zoneMap: {
        summary:
            'Live RFID zone map showing where workers are present right now.',
        items: [
            'Markers and occupancy use current zone positions.',
            'On Site / Zones / In Red are live snapshots, not historical.',
            'Click a zone to open its settings detail page.',
        ],
        source: 'zones · worker_positions · tracking headcount',
    },
    alertFeed: {
        summary:
            'Streaming list of the latest open and acknowledged alerts.',
        items: [
            'All / Crit toggles filter by severity in the browser.',
            'Titles and meta come from the alert payload (asset, zone, device).',
            'Updates live over Reverb with a 60s poll fallback.',
        ],
        source: 'alerts · AlertResource · Reverb alerts channel',
    },
    gasTrend: {
        summary:
            'Site-wide gas channel trend for the selected dashboard range.',
        items: [
            'Shows LEL, H₂S, O₂, CO, and CO₂ together.',
            'Uses hourly rollups for longer windows; falls back to raw readings when rollups are missing.',
            'Values are averaged across detectors at each timestamp.',
        ],
        source: 'gas_readings · gas_reading_rollups',
    },
    gasPanels: {
        summary:
            'Live channel gauges for every registered gas / CO₂ detector.',
        items: [
            'Colours follow active DOC-11 warning and alarm thresholds.',
            'Stale devices are highlighted when last reading is older than the health window.',
            'Live — not filtered by the dashboard date range.',
        ],
        source: 'latest gas_readings · gas_thresholds',
    },
    safetyScore: {
        summary:
            'Composite site safety score from PPE, zone control, and equipment readiness.',
        items: [
            'PPE component reflects confirmed violations in the selected range.',
            'Zone and equipment components use current operational state.',
            'Score is 0–100; lower PPE/equipment risk raises the score.',
        ],
        source: 'PPE summary · LSR open · equipment overdue',
    },
    ppeHeatmap: {
        summary:
            'Hour-of-day density of PPE violations in the selected range.',
        items: [
            'Rows are violation types (helmet, vest, harness, mask).',
            'Columns are hours covered by the selected window.',
            'False positives are excluded.',
        ],
        source: 'ppe_violations by detected_at hour',
    },
    lsrCategory: {
        summary:
            'LSR violations grouped by category for the selected range.',
        items: [
            'Counts include LSR records that occurred in the window.',
            'Open total at the top of the dashboard remains live open LSR.',
            'Empty categories are hidden.',
        ],
        source: 'lsr_violations · LsrService summary',
    },
    headcountFlow: {
        summary:
            'On-site headcount over time for the selected range.',
        items: [
            'Built from gate entry and exit events.',
            'Peak is the highest on-site count in the window.',
            'Useful for comparing manpower swings across shifts or days.',
        ],
        source: 'entry_exit_logs · TrackingService headcountFlow',
    },
    workersByZone: {
        summary:
            'Live distribution of workers currently present in each zone.',
        items: [
            'Uses the current RFID presence snapshot.',
            'Not historical — range filter does not change this chart.',
            'Zones with zero presence are omitted.',
        ],
        source: 'tracking headcount by_zone',
    },
    evacuation: {
        summary:
            'Readiness from the most recent evacuation / muster drill.',
        items: [
            'Accounted % combines muster reader, gate exit, and manual checks.',
            'Shows the last drill totals, not the dashboard date range.',
            'Trigger Evacuate from the header to open a live muster report.',
        ],
        source: 'evacuation reports · EvacuationService readiness',
    },
    openRecords: {
        summary:
            'Open HSE incidents and LSR records that still require action.',
        items: [
            'Always the current open set — not filtered by range.',
            'Action progress reflects mandatory action-taken fields.',
            'Closing an incident/LSR requires documented action (DOC-14).',
        ],
        source: 'hse_incidents · lsr_violations (open statuses)',
    },
} as const satisfies Record<string, SectionInfoContent>;
