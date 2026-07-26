<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report->report_number }}</title>
    <style>
        @page { margin: 28px 32px 36px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #0f172a;
            line-height: 1.4;
        }
        /* Brand tokens (print-safe) */
        .c-navy { color: #0f172a; }
        .c-cyan { color: #0e7490; }
        .c-ok { color: #15803d; }
        .c-warn { color: #b45309; }
        .c-crit { color: #b91c1c; }
        .c-muted { color: #64748b; }

        .cover {
            background: #0f172a;
            color: #f8fafc;
            padding: 18px 20px 16px;
            margin: -4px -4px 14px;
            border-radius: 4px;
        }
        .cover-brand {
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #67e8f9;
            margin: 0 0 6px;
        }
        .cover h1 {
            font-size: 22px;
            margin: 0 0 4px;
            color: #fff;
            letter-spacing: -0.3px;
        }
        .cover-sub {
            font-size: 11px;
            color: #94a3b8;
            margin: 0 0 12px;
        }
        .cover-meta {
            width: 100%;
            border-collapse: collapse;
        }
        .cover-meta td {
            border: none;
            padding: 3px 14px 3px 0;
            vertical-align: top;
            font-size: 10px;
            color: #cbd5e1;
        }
        .cover-meta .label {
            display: block;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #67e8f9;
            margin-bottom: 1px;
        }
        .cover-meta .value {
            color: #f8fafc;
            font-weight: bold;
            font-size: 11px;
        }

        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .pill-ok { background: #dcfce7; color: #15803d; }
        .pill-warn { background: #fef3c7; color: #b45309; }
        .pill-crit { background: #fee2e2; color: #b91c1c; }
        .pill-accent { background: #cffafe; color: #0e7490; }
        .pill-neutral { background: #e2e8f0; color: #334155; }
        .pill-dark { background: #164e63; color: #a5f3fc; }

        h2.section {
            font-size: 13px;
            margin: 20px 0 8px;
            padding: 7px 10px;
            background: #ecfeff;
            border-left: 4px solid #0e7490;
            color: #0f172a;
        }
        h2.section .badge {
            float: right;
            margin-top: -1px;
        }
        h3 {
            font-size: 11px;
            margin: 12px 0 6px;
            color: #0e7490;
        }

        .badge {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 2px 7px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-left: 4px;
            vertical-align: middle;
        }
        .badge-auto { background: #dcfce7; color: #15803d; }
        .badge-manual { background: #fef3c7; color: #b45309; }
        .badge-mix { background: #cffafe; color: #0e7490; }

        .note {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            border-left: 4px solid #b45309;
            padding: 7px 10px;
            margin: 6px 0;
            border-radius: 2px;
            font-size: 10px;
            color: #78350f;
        }

        .kpis {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 4px 0 8px;
        }
        .kpis td {
            width: 33.33%;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #94a3b8;
            padding: 8px 9px;
            border-radius: 2px;
        }
        .kpis td.tone-ok { border-top-color: #22c55e; background: #f0fdf4; }
        .kpis td.tone-warn { border-top-color: #f59e0b; background: #fffbeb; }
        .kpis td.tone-crit { border-top-color: #ef4444; background: #fef2f2; }
        .kpis td.tone-accent { border-top-color: #06b6d4; background: #ecfeff; }
        .kpis td.tone-neutral { border-top-color: #64748b; background: #f8fafc; }
        .kpi-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            margin: 0 0 3px;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px;
            line-height: 1.15;
        }
        .kpi-detail {
            font-size: 8.5px;
            color: #475569;
            line-height: 1.3;
            margin: 0;
        }

        .stat-strip {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 8px;
            background: #f1f5f9;
        }
        .stat-strip td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: center;
            width: 25%;
        }
        .stat-strip .num {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #0e7490;
        }
        .stat-strip .lbl {
            display: block;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-top: 1px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 6px;
        }
        table.data th, table.data td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
            font-size: 9.5px;
        }
        table.data th {
            background: #0e7490;
            color: #ecfeff;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: bold;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.data td.day { white-space: nowrap; color: #334155; font-weight: bold; width: 52px; }

        .muted { color: #64748b; font-size: 9.5px; }
        .empty {
            color: #64748b;
            font-style: italic;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            margin: 4px 0;
        }
        .lead {
            margin: 0 0 8px;
            font-size: 10.5px;
            color: #334155;
        }
        .page-break { page-break-before: always; }
        .footer-note {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }

        .appendix-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #0e7490;
            padding: 6px 9px;
            margin: 4px 0;
            font-size: 9.5px;
        }
        .appendix-box strong { color: #0e7490; }
    </style>
</head>
<body>
@php
    $sections = [
        'i_daily_safety_observations' => 'i. Daily Safety Observations',
        'ii_hse_incidents' => 'ii. HSE Accidents & Incidents',
        'iii_lsr_violations' => 'iii. LSR Violations & Actions Taken',
        'iv_weather' => 'iv. Weather Conditions',
        'v_manpower' => 'v. Site Manpower',
        'vi_units_monitored' => 'vi. Total Vehicles/Units Monitored',
        'vii_vehicle_violations' => 'vii. Vehicle Violations & Actions Taken',
        'viii_environmental' => 'viii. Environmental Data',
        'ix_gas' => 'ix. Gas Monitoring (LEL / H₂S / O₂ / CO / CO₂)',
    ];
    $notes = collect($data['completeness']['notes'] ?? []);

    $labelize = static function (?string $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return ucwords(str_replace('_', ' ', $value));
    };

    $fmt = static function ($value, int $digits = 1): string {
        if ($value === null || $value === '' || (is_float($value) && is_nan($value))) {
            return '—';
        }

        if (is_int($value) || (is_numeric($value) && floor((float) $value) == $value)) {
            return (string) (int) $value;
        }

        return number_format((float) $value, $digits);
    };

    $ppeDays = $data['i_daily_safety_observations']['per_day'] ?? [];
    $ppeTotal = collect($ppeDays)->sum('total');
    $fpExcluded = (int) ($data['i_daily_safety_observations']['false_positives_excluded'] ?? 0);
    $incidents = $data['ii_hse_incidents'] ?? [];
    $lsrEntries = $data['iii_lsr_violations']['entries'] ?? [];
    $lsrSummary = $data['iii_lsr_violations']['summary_by_category'] ?? [];
    $weatherDays = $data['iv_weather']['per_day'] ?? [];
    $manpowerDays = $data['v_manpower']['per_day'] ?? [];
    $vehicles = $data['vii_vehicle_violations'] ?? [];
    $envDays = $data['viii_environmental']['per_day'] ?? [];
    $gasDays = $data['ix_gas']['per_day'] ?? [];
    $gasAlarms = $data['ix_gas']['alarm_events'] ?? [];

    $tempAvgs = collect($weatherDays)->map(fn ($d) => $d['temp']['avg'] ?? null)->filter(fn ($v) => $v !== null);
    $humidityAvgs = collect($weatherDays)->map(fn ($d) => $d['humidity']['avg'] ?? null)->filter(fn ($v) => $v !== null);
    $windAvgs = collect($weatherDays)->map(fn ($d) => $d['wind']['avg'] ?? null)->filter(fn ($v) => $v !== null);
    $peakManpower = collect($manpowerDays)->max('peak');
    $avgManpower = collect($manpowerDays)->avg('average');

    $gasChannelAvg = static function (string $channel) use ($gasDays) {
        $vals = collect($gasDays)
            ->map(fn ($d) => $d[$channel]['avg'] ?? null)
            ->filter(fn ($v) => $v !== null);

        return $vals->isEmpty() ? null : $vals->avg();
    };

    $mam = static function (?array $stats) use ($fmt): string {
        if ($stats === null) {
            return '—';
        }

        return $fmt($stats['min'] ?? null).' / '.$fmt($stats['avg'] ?? null).' / '.$fmt($stats['max'] ?? null);
    };

    $compactDate = static function (?string $date): string {
        if ($date === null || $date === '') {
            return '—';
        }
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('D j M');
        } catch (\Throwable) {
            return $date;
        }
    };

    $ppeByType = [];
    foreach ($ppeDays as $day) {
        foreach (($day['by_type'] ?? []) as $type => $count) {
            $ppeByType[$type] = ($ppeByType[$type] ?? 0) + (int) $count;
        }
    }
    arsort($ppeByType);
    $byTypeLine = static function (array $byType, int $limit = 3) use ($labelize): string {
        $parts = [];
        $i = 0;
        foreach ($byType as $type => $count) {
            if ((int) $count <= 0) {
                continue;
            }
            $parts[] = $labelize((string) $type).' ('.$count.')';
            $i++;
            if ($i >= $limit) {
                break;
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    };

    $incidentSeverities = [];
    foreach ($incidents as $row) {
        $sev = (string) ($row['severity'] ?? '');
        if ($sev !== '') {
            $incidentSeverities[$sev] = ($incidentSeverities[$sev] ?? 0) + 1;
        }
    }
    arsort($incidentSeverities);

    $vehicleTypes = [];
    foreach ($vehicles as $row) {
        $type = (string) ($row['violation_type'] ?? '');
        if ($type !== '') {
            $vehicleTypes[$type] = ($vehicleTypes[$type] ?? 0) + 1;
        }
    }
    arsort($vehicleTypes);

    $envSampled = 0;
    $envParams = [];
    foreach ($envDays as $day) {
        $air = $day['air_quality'] ?? [];
        if (is_array($air) && $air !== []) {
            $envSampled++;
            foreach (array_keys($air) as $param) {
                $envParams[$param] = true;
            }
        }
    }
    $envParamList = array_slice(array_keys($envParams), 0, 3);

    $ppeTop = $byTypeLine($ppeByType, 2);

    $badgeClass = static function (?string $badge): string {
        $b = strtolower((string) $badge);
        if (str_contains($b, 'manual') && str_contains($b, 'automat')) {
            return 'badge badge-mix';
        }
        if (str_contains($b, 'manual')) {
            return 'badge badge-manual';
        }
        if (str_contains($b, 'automat')) {
            return 'badge badge-auto';
        }

        return 'badge';
    };

    $severityPill = static function (?string $severity): string {
        $s = strtolower((string) $severity);

        return match (true) {
            str_contains($s, 'critical') || $s === 'alarm' => 'pill pill-crit',
            str_contains($s, 'high') || str_contains($s, 'warning') || $s === 'major' => 'pill pill-warn',
            str_contains($s, 'low') || str_contains($s, 'minor') || $s === 'info' => 'pill pill-accent',
            default => 'pill pill-neutral',
        };
    };

    $statusPillClass = match ($report->status->value) {
        'published' => 'pill pill-ok',
        'generated' => 'pill pill-accent',
        default => 'pill pill-warn',
    };

    $summaryRows = [
        [
            'item' => 'i. Safety observations',
            'figure' => (string) $ppeTotal,
            'detail' => ($ppeTop === '—' ? 'No confirmed events' : $ppeTop).($fpExcluded > 0 ? ' · '.$fpExcluded.' FP excluded' : ''),
            'tone' => $ppeTotal > 0 ? 'warn' : 'ok',
        ],
        [
            'item' => 'ii. HSE incidents',
            'figure' => (string) count($incidents),
            'detail' => count($incidents) === 0 ? 'None logged' : $byTypeLine($incidentSeverities, 3),
            'tone' => count($incidents) > 0 ? 'crit' : 'ok',
        ],
        [
            'item' => 'iii. LSR violations',
            'figure' => (string) count($lsrEntries),
            'detail' => collect($lsrSummary)
                ->map(fn ($r) => $labelize($r['category'] ?? '').' ('.($r['count'] ?? 0).')')
                ->implode(', ') ?: 'None logged',
            'tone' => count($lsrEntries) > 0 ? 'warn' : 'ok',
        ],
        [
            'item' => 'iv. Weather',
            'figure' => $fmt($tempAvgs->avg()).' °C',
            'detail' => 'RH '.$fmt($humidityAvgs->avg(), 0).'% · Wind '.$fmt($windAvgs->avg()).' m/s',
            'tone' => 'neutral',
        ],
        [
            'item' => 'v. Manpower',
            'figure' => $fmt($peakManpower, 0),
            'detail' => 'Peak · avg '.$fmt($avgManpower, 0).'/day',
            'tone' => 'accent',
        ],
        [
            'item' => 'vi. Units monitored',
            'figure' => (string) ($data['vi_units_monitored']['count'] ?? 0),
            'detail' => 'Active field units',
            'tone' => 'neutral',
        ],
        [
            'item' => 'vii. Vehicle violations',
            'figure' => (string) count($vehicles),
            'detail' => count($vehicles) === 0 ? 'None logged' : $byTypeLine($vehicleTypes, 2),
            'tone' => count($vehicles) > 0 ? 'warn' : 'ok',
        ],
        [
            'item' => 'viii. Environmental',
            'figure' => (string) $envSampled,
            'detail' => $envParamList === []
                ? 'No air-quality samples'
                : $envSampled.'/'.count($envDays).' days · '.implode(', ', $envParamList),
            'tone' => 'neutral',
        ],
        [
            'item' => 'ix. Gas monitoring',
            'figure' => (string) count($gasAlarms),
            'detail' => 'LEL '.$fmt($gasChannelAvg('lel')).'% · H₂S '.$fmt($gasChannelAvg('h2s')).' · O₂ '.$fmt($gasChannelAvg('o2')).'% · CO '.$fmt($gasChannelAvg('co')).' · CO₂ '.$fmt($gasChannelAvg('co2'), 0),
            'tone' => count($gasAlarms) > 0 ? 'crit' : 'ok',
        ],
    ];

    $periodStart = $data['period']['start'] ?? $report->period_start;
    $periodEnd = $data['period']['end'] ?? $report->period_end;
@endphp

    {{-- Cover --}}
    <div class="cover">
        <div class="cover-brand">IR4 · Safety command centre</div>
        <h1>Weekly Report {{ $report->report_number }}</h1>
        <p class="cover-sub">
            Frozen compliance snapshot
            · <span class="{{ $statusPillClass }}">{{ ucfirst($report->status->value) }}</span>
            @if($report->supersedes)
                · Amendment of {{ $report->supersedes->report_number }}
            @endif
        </p>
        <table class="cover-meta">
            <tr>
                <td>
                    <span class="label">Period</span>
                    <span class="value">{{ $periodStart }} → {{ $periodEnd }}</span>
                </td>
                <td>
                    <span class="label">Generated</span>
                    <span class="value">{{ $report->generated_at }}</span>
                </td>
                <td>
                    <span class="label">Published</span>
                    <span class="value">{{ $report->published_at ?? 'Not yet published' }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Headline strip --}}
    <table class="stat-strip">
        <tr>
            <td>
                <span class="num">{{ $ppeTotal }}</span>
                <span class="lbl">PPE events</span>
            </td>
            <td>
                <span class="num">{{ count($incidents) }}</span>
                <span class="lbl">Incidents</span>
            </td>
            <td>
                <span class="num">{{ count($lsrEntries) }}</span>
                <span class="lbl">LSR</span>
            </td>
            <td>
                <span class="num">{{ count($gasAlarms) }}</span>
                <span class="lbl">Gas alarms</span>
            </td>
        </tr>
    </table>

    <h2 class="section">Weekly summary <span class="badge badge-mix">9 items</span></h2>
    <p class="muted" style="margin-top:-4px;margin-bottom:6px;">Headline figure and concise detail for each report item. Colour marks attention (green = clear, amber = watch, red = action).</p>

    @foreach(array_chunk($summaryRows, 3) as $chunk)
        <table class="kpis">
            <tr>
                @foreach($chunk as $row)
                    <td class="tone-{{ $row['tone'] }}">
                        <p class="kpi-label">{{ $row['item'] }}</p>
                        <p class="kpi-value">{{ $row['figure'] }}</p>
                        <p class="kpi-detail">{{ $row['detail'] }}</p>
                    </td>
                @endforeach
                @for($i = count($chunk); $i < 3; $i++)
                    <td style="border:none;background:transparent;"></td>
                @endfor
            </tr>
        </table>
    @endforeach

    @if($notes->isNotEmpty())
        <h2 class="section">Data completeness</h2>
        <p class="muted" style="margin-top:-4px;">Sensor outages above the completeness threshold — declared, not hidden.</p>
        @foreach($notes as $note)
            <div class="note">
                <strong>{{ $sections[$note['item']] ?? $labelize($note['item'] ?? '') }}:</strong>
                {{ $note['message'] }}
            </div>
        @endforeach
    @endif

    <h2 class="section page-break">Detailed report</h2>
    <p class="lead">Full tables for items i–ix. Automation badges show how each item was produced.</p>

    @foreach($sections as $key => $title)
        @php $badge = $badges[$key] ?? ''; @endphp
        <h2 class="section">
            {{ $title }}
            @if($badge !== '')
                <span class="{{ $badgeClass($badge) }}">{{ $badge }}</span>
            @endif
        </h2>
        @foreach($notes->where('item', $key) as $note)
            <div class="note">{{ $note['message'] }}</div>
        @endforeach

        @if($key === 'i_daily_safety_observations')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $ppeTotal }}</span><span class="lbl">Confirmed</span></td>
                    <td><span class="num">{{ $fpExcluded }}</span><span class="lbl">False positives excluded</span></td>
                    <td><span class="num">{{ count($data[$key]['by_camera'] ?? []) }}</span><span class="lbl">Cameras</span></td>
                    <td><span class="num">{{ count($ppeDays) }}</span><span class="lbl">Days covered</span></td>
                </tr>
            </table>
            @if(count($ppeDays) === 0)
                <p class="empty">No daily observations in this period.</p>
            @else
                <table class="data">
                    <thead><tr><th>Date</th><th>Total</th><th>By type</th></tr></thead>
                    <tbody>
                    @foreach($ppeDays as $row)
                        <tr>
                            <td class="day">{{ $compactDate($row['date'] ?? null) }}</td>
                            <td class="num">{{ $row['total'] ?? 0 }}</td>
                            <td>{{ $byTypeLine($row['by_type'] ?? []) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            @if(!empty($data[$key]['by_camera']))
                <h3>By camera</h3>
                <table class="data">
                    <thead><tr><th>Camera</th><th>Total</th></tr></thead>
                    <tbody>
                    @foreach($data[$key]['by_camera'] as $row)
                        <tr>
                            <td>{{ $row['camera'] ?? '' }}</td>
                            <td class="num">{{ $row['total'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'ii_hse_incidents')
            @if(count($incidents) === 0)
                <p class="empty">No HSE incidents logged in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Occurred</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Immediate action</th>
                            <th>Corrective action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($incidents as $row)
                        <tr>
                            <td><strong>{{ $row['incident_number'] ?? '' }}</strong></td>
                            <td>{{ $row['occurred_at'] ?? '' }}</td>
                            <td>{{ $labelize($row['type'] ?? null) }}</td>
                            <td><span class="{{ $severityPill($row['severity'] ?? null) }}">{{ $labelize($row['severity'] ?? null) }}</span></td>
                            <td><span class="pill pill-neutral">{{ $labelize($row['status'] ?? null) }}</span></td>
                            <td>{{ $row['immediate_action'] ?? '—' }}</td>
                            <td>{{ $row['corrective_action'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'iii_lsr_violations')
            @if(count($lsrSummary) > 0)
                <p style="margin:0 0 6px;">
                    @foreach($lsrSummary as $row)
                        <span class="pill pill-warn">{{ $labelize($row['category'] ?? null) }} · {{ $row['count'] ?? 0 }}</span>
                        &nbsp;
                    @endforeach
                </p>
            @endif
            @if(count($lsrEntries) === 0)
                <p class="empty">No LSR violations logged in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Occurred</th>
                            <th>Worker</th>
                            <th>Zone</th>
                            <th>Action taken</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($lsrEntries as $row)
                        <tr>
                            <td><span class="pill pill-warn">{{ $labelize($row['category'] ?? null) }}</span></td>
                            <td>{{ $row['occurred_at'] ?? '' }}</td>
                            <td>{{ $row['worker'] ?? '—' }}</td>
                            <td>{{ $row['zone'] ?? '—' }}</td>
                            <td>{{ $row['action_taken'] ?? '—' }}</td>
                            <td><span class="pill pill-neutral">{{ $labelize($row['status'] ?? null) }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'iv_weather')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $fmt($tempAvgs->avg()) }}°</span><span class="lbl">Avg temp</span></td>
                    <td><span class="num">{{ $fmt($humidityAvgs->avg(), 0) }}%</span><span class="lbl">Avg RH</span></td>
                    <td><span class="num">{{ $fmt($windAvgs->avg()) }}</span><span class="lbl">Avg wind m/s</span></td>
                    <td><span class="num">{{ count($weatherDays) }}</span><span class="lbl">Days</span></td>
                </tr>
            </table>
            @if(count($weatherDays) === 0)
                <p class="empty">No weather data in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Temp °C (min / avg / max)</th>
                            <th>Humidity % (min / avg / max)</th>
                            <th>Wind m/s (min / avg / max)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($weatherDays as $row)
                        <tr>
                            <td class="day">{{ $compactDate($row['date'] ?? null) }}</td>
                            <td class="num">{{ $mam(isset($row['temp']) && is_array($row['temp']) ? $row['temp'] : null) }}</td>
                            <td class="num">{{ $mam(isset($row['humidity']) && is_array($row['humidity']) ? $row['humidity'] : null) }}</td>
                            <td class="num">{{ $mam(isset($row['wind']) && is_array($row['wind']) ? $row['wind'] : null) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'v_manpower')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $fmt($peakManpower, 0) }}</span><span class="lbl">Peak</span></td>
                    <td><span class="num">{{ $fmt($avgManpower, 0) }}</span><span class="lbl">Daily avg</span></td>
                    <td><span class="num">{{ $fmt(collect($manpowerDays)->sum('entries'), 0) }}</span><span class="lbl">Entries</span></td>
                    <td><span class="num">{{ $fmt(collect($manpowerDays)->sum('exits'), 0) }}</span><span class="lbl">Exits</span></td>
                </tr>
            </table>
            @if(count($manpowerDays) === 0)
                <p class="empty">No manpower data in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Peak</th>
                            <th>Average</th>
                            <th>Entries</th>
                            <th>Exits</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($manpowerDays as $row)
                        <tr>
                            <td class="day">{{ $compactDate($row['date'] ?? null) }}</td>
                            <td class="num">{{ $fmt($row['peak'] ?? null, 0) }}</td>
                            <td class="num">{{ $fmt($row['average'] ?? null) }}</td>
                            <td class="num">{{ $fmt($row['entries'] ?? null, 0) }}</td>
                            <td class="num">{{ $fmt($row['exits'] ?? null, 0) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'vi_units_monitored')
            <table class="stat-strip">
                <tr>
                    <td colspan="2"><span class="num">{{ $data[$key]['count'] ?? 0 }}</span><span class="lbl">Active field units</span></td>
                    <td colspan="2"><span class="lbl" style="font-size:9px;text-transform:none;letter-spacing:0;color:#475569;">{{ $data[$key]['note'] ?? '' }}</span></td>
                </tr>
            </table>

        @elseif($key === 'vii_vehicle_violations')
            @if(count($vehicles) === 0)
                <p class="empty">No vehicle violations logged in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Observed</th>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Action taken</th>
                            <th>Logged by</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($vehicles as $row)
                        <tr>
                            <td>{{ $row['observed_at'] ?? '' }}</td>
                            <td><strong>{{ $row['vehicle_description'] ?? '' }}</strong></td>
                            <td><span class="pill pill-warn">{{ $labelize($row['violation_type'] ?? null) }}</span></td>
                            <td>{{ $row['description'] ?? '—' }}</td>
                            <td>{{ $row['action_taken'] ?? '—' }}</td>
                            <td>{{ $row['logged_by'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'viii_environmental')
            @if(count($envDays) === 0)
                <p class="empty">No environmental data in this period.</p>
            @else
                <table class="data">
                    <thead><tr><th>Day</th><th>Air quality</th></tr></thead>
                    <tbody>
                    @foreach($envDays as $row)
                        @php
                            $air = $row['air_quality'] ?? [];
                            $airParts = [];
                            foreach ($air as $k => $v) {
                                if (is_array($v)) {
                                    $airParts[] = $labelize((string) $k).': '.$mam($v);
                                } elseif ($v !== null && $v !== '') {
                                    $airParts[] = $labelize((string) $k).': '.$fmt($v);
                                }
                            }
                        @endphp
                        <tr>
                            <td class="day">{{ $compactDate($row['date'] ?? null) }}</td>
                            <td>{{ $airParts === [] ? 'No air-quality samples' : implode(' · ', $airParts) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'ix_gas')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $fmt($gasChannelAvg('lel')) }}</span><span class="lbl">LEL % avg</span></td>
                    <td><span class="num">{{ $fmt($gasChannelAvg('h2s')) }}</span><span class="lbl">H₂S avg</span></td>
                    <td><span class="num">{{ $fmt($gasChannelAvg('o2')) }}</span><span class="lbl">O₂ % avg</span></td>
                    <td><span class="num">{{ count($gasAlarms) }}</span><span class="lbl">Alarms</span></td>
                </tr>
            </table>
            <p class="muted">Daily cells show min / avg / max. CO avg {{ $fmt($gasChannelAvg('co')) }} · CO₂ avg {{ $fmt($gasChannelAvg('co2'), 0) }} ppm.</p>
            @if(count($gasDays) === 0)
                <p class="empty">No gas readings in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>LEL %</th>
                            <th>H₂S ppm</th>
                            <th>O₂ %</th>
                            <th>CO ppm</th>
                            <th>CO₂ ppm</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($gasDays as $row)
                        <tr>
                            <td class="day">{{ $compactDate($row['date'] ?? null) }}</td>
                            <td class="num">{{ $mam(isset($row['lel']) && is_array($row['lel']) ? $row['lel'] : null) }}</td>
                            <td class="num">{{ $mam(isset($row['h2s']) && is_array($row['h2s']) ? $row['h2s'] : null) }}</td>
                            <td class="num">{{ $mam(isset($row['o2']) && is_array($row['o2']) ? $row['o2'] : null) }}</td>
                            <td class="num">{{ $mam(isset($row['co']) && is_array($row['co']) ? $row['co'] : null) }}</td>
                            <td class="num">{{ $mam(isset($row['co2']) && is_array($row['co2']) ? $row['co2'] : null) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            <h3>Alarm events</h3>
            @if(count($gasAlarms) === 0)
                <p class="empty">No gas alarm events.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Triggered</th>
                            <th>Device</th>
                            <th>Gas</th>
                            <th>Level</th>
                            <th>Peak</th>
                            <th>Duration</th>
                            <th>Acknowledged by</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($gasAlarms as $row)
                        <tr>
                            <td>{{ $row['triggered_at'] ?? '' }}</td>
                            <td>{{ $row['device'] ?? '—' }}</td>
                            <td>{{ $labelize($row['gas'] ?? null) }}</td>
                            <td><span class="{{ $severityPill($row['level'] ?? null) }}">{{ $labelize($row['level'] ?? null) }}</span></td>
                            <td class="num">{{ $fmt($row['peak'] ?? null) }}</td>
                            <td class="num">{{ isset($row['duration_s']) ? $row['duration_s'].'s' : '—' }}</td>
                            <td>{{ $row['acknowledged_by'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @endif
    @endforeach

    <h2 class="section page-break">Appendix — Action taken</h2>
    <p class="lead">Mandatory action text from incidents, LSR, and vehicle violations — for reviewer scan.</p>

    <h3>Incidents</h3>
    @if(count($incidents) === 0)
        <p class="empty">None.</p>
    @else
        @foreach($incidents as $row)
            <div class="appendix-box">
                <strong>{{ $row['incident_number'] ?? '' }}</strong>
                <span class="{{ $severityPill($row['severity'] ?? null) }}">{{ $labelize($row['severity'] ?? null) }}</span>
                <br>
                Immediate: {{ $row['immediate_action'] ?? '—' }}
                <br>
                Corrective: {{ $row['corrective_action'] ?? '—' }}
            </div>
        @endforeach
    @endif

    <h3>LSR</h3>
    @if(count($lsrEntries) === 0)
        <p class="empty">None.</p>
    @else
        @foreach($lsrEntries as $row)
            <div class="appendix-box">
                <strong>{{ $labelize($row['category'] ?? null) }}</strong>
                · {{ $row['zone'] ?? '—' }}
                <br>
                {{ $row['action_taken'] ?? '—' }}
            </div>
        @endforeach
    @endif

    <h3>Vehicle violations</h3>
    @if(count($vehicles) === 0)
        <p class="empty">None.</p>
    @else
        @foreach($vehicles as $row)
            <div class="appendix-box">
                <strong>{{ $row['vehicle_description'] ?? '' }}</strong>
                <span class="pill pill-warn">{{ $labelize($row['violation_type'] ?? null) }}</span>
                <br>
                {{ $row['action_taken'] ?? '—' }}
            </div>
        @endforeach
    @endif

    <div class="footer-note">
        IR4 Weekly Report {{ $report->report_number }} · Data frozen at generation · {{ $periodStart }} → {{ $periodEnd }}
    </div>
</body>
</html>
