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
        h2.section {
            font-size: 13px;
            margin: 20px 0 8px;
            padding: 7px 10px;
            background: #ecfeff;
            border-left: 4px solid #0e7490;
            color: #0f172a;
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
        .blurb { color: #64748b; font-size: 10px; margin: 0 0 8px; }
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
        table.data td.day { white-space: nowrap; color: #334155; font-weight: bold; }
        .muted { color: #64748b; font-size: 9.5px; }
        .empty {
            color: #64748b;
            font-style: italic;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            margin: 4px 0;
        }
        .empty .hint { display: block; margin-top: 3px; font-size: 9px; font-style: normal; }
        .lead { margin: 0 0 8px; font-size: 10.5px; color: #334155; }
        .page-break { page-break-before: always; }
        .footer-note {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }
        .glance { margin: 0 0 10px; padding-left: 18px; }
        .glance li { margin: 0 0 4px; }
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
    /** @var \App\Services\Report\WeeklyReportPresenter $view */
    $executive = $view->executiveLines();
    $summaryRows = $view->summaryTiles();
    $sections = $view->sections();
    $ppe = $view->ppeSection();
    $incidents = $view->incidentsSection();
    $lsr = $view->lsrSection();
    $weather = $view->weatherSection();
    $manpower = $view->manpowerSection();
    $units = $view->unitsSection();
    $vehicles = $view->vehiclesSection();
    $gas = $view->gasSection();
    $periodStart = $data['period']['start'] ?? $report->period_start;
    $periodEnd = $data['period']['end'] ?? $report->period_end;

    $badgeClass = static function (?string $badge): string {
        $b = strtolower((string) $badge);
        if (str_contains($b, 'manual') && str_contains($b, 'automat')) {
            return 'badge badge-mix';
        }
        if (str_contains($b, 'manual')) {
            return 'badge badge-manual';
        }

        return 'badge badge-auto';
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
@endphp

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

    <h2 class="section">At a glance</h2>
    <p class="muted" style="margin-top:-4px;">What a reviewer needs to know first</p>
    <ul class="glance">
        @foreach($executive as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    <h2 class="section">Weekly summary <span class="badge badge-mix">{{ count($summaryRows) }} items</span></h2>
    <p class="muted" style="margin-top:-4px;margin-bottom:6px;">One tile per report item. Colour marks attention (green = clear, amber = watch, red = action).</p>

    @foreach(array_chunk($summaryRows, 3) as $chunk)
        <table class="kpis">
            <tr>
                @foreach($chunk as $row)
                    <td class="tone-{{ $row['tone'] }}">
                        <p class="kpi-label">{{ $row['label'] }}</p>
                        <p class="kpi-value">{{ $row['value'] }}</p>
                        <p class="kpi-detail">{{ $row['detail'] }}</p>
                    </td>
                @endforeach
                @for($i = count($chunk); $i < 3; $i++)
                    <td style="border:none;background:transparent;"></td>
                @endfor
            </tr>
        </table>
    @endforeach

    <h2 class="section page-break">Detailed report</h2>
    <p class="lead">Full tables for each report item. Automation badges show how each item was produced.</p>

    @foreach($sections as $section)
        @php
            $key = $section['key'];
            $badge = $badges[$key] ?? '';
            $coverage = $view->coverageMessage($key);
        @endphp
        <h2 class="section">
            {{ $section['title'] }}
            @if($badge !== '')
                <span class="{{ $badgeClass($badge) }}">{{ $badge }}</span>
            @endif
        </h2>
        <p class="blurb">{{ $section['blurb'] }}</p>
        @if($coverage)
            <div class="note">{{ $coverage }}</div>
        @endif

        @if($key === 'i_daily_safety_observations')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $ppe['total'] }}</span><span class="lbl">Confirmed</span></td>
                    <td><span class="num">{{ $ppe['cameras_reporting'] }}</span><span class="lbl">Cameras reporting</span></td>
                </tr>
            </table>
            @if($ppe['type_pills'] !== [])
                <p style="margin:0 0 6px;">
                    @foreach($ppe['type_pills'] as $pill)
                        <span class="pill pill-warn">{{ $pill['label'] }} · {{ $pill['count'] }}</span>&nbsp;
                    @endforeach
                </p>
            @endif
            @if($ppe['empty_label'])
                <p class="empty">{{ $ppe['empty_label'] }}<span class="hint">{{ $ppe['empty_hint'] }}</span></p>
            @else
                <table class="data">
                    <thead><tr><th>Date</th><th>Total</th><th>By type</th></tr></thead>
                    <tbody>
                    @foreach($ppe['per_day'] as $row)
                        <tr>
                            <td class="day">{{ $row['date'] }}</td>
                            <td class="num">{{ $row['total'] }}</td>
                            <td>{{ $row['types'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            @if($ppe['by_camera'] !== [])
                <h3>By camera</h3>
                <table class="data">
                    <thead><tr><th>Camera</th><th>Total</th></tr></thead>
                    <tbody>
                    @foreach($ppe['by_camera'] as $row)
                        <tr>
                            <td>{{ $row['camera'] }}</td>
                            <td class="num">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'ii_hse_incidents')
            @if($incidents['empty_label'])
                <p class="empty">{{ $incidents['empty_label'] }}<span class="hint">{{ $incidents['empty_hint'] }}</span></p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Number</th><th>Occurred</th><th>Type</th><th>Severity</th>
                            <th>Status</th><th>Immediate action</th><th>Corrective action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($incidents['rows'] as $row)
                        <tr>
                            <td><strong>{{ $row['number'] }}</strong></td>
                            <td>{{ $row['when'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td><span class="{{ $severityPill($row['severity']) }}">{{ $row['severity'] }}</span></td>
                            <td><span class="pill pill-neutral">{{ $row['status'] }}</span></td>
                            <td>{{ $row['immediate'] }}</td>
                            <td>{{ $row['corrective'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'iii_lsr_violations')
            @if($lsr['summary'] !== [])
                <p style="margin:0 0 6px;">
                    @foreach($lsr['summary'] as $row)
                        <span class="pill pill-warn">{{ $row['category'] }} · {{ $row['count'] }}</span>&nbsp;
                    @endforeach
                </p>
            @endif
            @if($lsr['empty_label'])
                <p class="empty">{{ $lsr['empty_label'] }}<span class="hint">{{ $lsr['empty_hint'] }}</span></p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Category</th><th>Occurred</th><th>Worker</th>
                            <th>Zone</th><th>Action taken</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($lsr['rows'] as $row)
                        <tr>
                            <td><span class="pill pill-warn">{{ $row['category'] }}</span></td>
                            <td>{{ $row['when'] }}</td>
                            <td>{{ $row['worker'] }}</td>
                            <td>{{ $row['zone'] }}</td>
                            <td>{{ $row['action'] }}</td>
                            <td><span class="pill pill-neutral">{{ $row['status'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'iv_weather')
            @if($weather['empty_label'])
                <p class="empty">{{ $weather['empty_label'] }}<span class="hint">{{ $weather['empty_hint'] }}</span></p>
            @else
                <table class="stat-strip">
                    <tr>
                        <td><span class="num">{{ $weather['week_avg_temp'] }}</span><span class="lbl">Week avg temperature</span></td>
                        <td><span class="num">{{ $weather['week_avg_humidity'] }}</span><span class="lbl">Week avg humidity</span></td>
                    </tr>
                </table>
                <table class="data">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Temp °C (min · avg · max)</th>
                            <th>Humidity % (min · avg · max)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($weather['rows'] as $row)
                        <tr>
                            <td class="day">{{ $row['date'] }}</td>
                            <td>{{ $row['temp'] }}</td>
                            <td>{{ $row['humidity'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'v_manpower')
            @if($manpower['empty_label'])
                <p class="empty">{{ $manpower['empty_label'] }}<span class="hint">{{ $manpower['empty_hint'] }}</span></p>
            @else
                @php
                    $manpowerColumns = $manpower['columns'] ?? ['date', 'peak', 'average'];
                    $manpowerLabels = [
                        'date' => 'Date',
                        'peak' => 'Peak',
                        'average' => 'Average',
                    ];
                @endphp
                <table class="data">
                    <thead>
                        <tr>
                            @foreach($manpowerColumns as $col)
                                <th>{{ $manpowerLabels[$col] ?? $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($manpower['rows'] as $row)
                        <tr>
                            @foreach($manpowerColumns as $col)
                                <td class="{{ $col === 'date' ? 'day' : 'num' }}">{{ $row[$col] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'vi_units_monitored')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $units['count'] }}</span><span class="lbl">Active units</span></td>
                    <td>
                        <span class="lbl">What this counts</span>
                        <span class="num" style="font-size:10px;font-weight:normal;color:#334155;">{{ $units['what'] }}</span>
                    </td>
                </tr>
            </table>
            <p class="muted">{{ $units['note'] }}</p>

        @elseif($key === 'vii_vehicle_violations')
            @if($vehicles['empty_label'])
                <p class="empty">{{ $vehicles['empty_label'] }}<span class="hint">{{ $vehicles['empty_hint'] }}</span></p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Observed</th><th>Vehicle</th><th>Type</th>
                            <th>Description</th><th>Action taken</th><th>Logged by</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($vehicles['rows'] as $row)
                        <tr>
                            <td>{{ $row['when'] }}</td>
                            <td><strong>{{ $row['vehicle'] }}</strong></td>
                            <td><span class="pill pill-warn">{{ $row['type'] }}</span></td>
                            <td>{{ $row['description'] }}</td>
                            <td>{{ $row['action'] }}</td>
                            <td>{{ $row['by'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

        @elseif($key === 'ix_gas')
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $gas['alarm_count'] }}</span><span class="lbl">Alarm events</span></td>
                    <td><span class="num">{{ $gas['during_outage'] }}</span><span class="lbl">During outage</span></td>
                </tr>
            </table>
            <table class="stat-strip">
                <tr>
                    <td><span class="num">{{ $gas['week_avgs']['lel'] }}</span><span class="lbl">Week avg LEL</span></td>
                    <td><span class="num">{{ $gas['week_avgs']['h2s'] }}</span><span class="lbl">Week avg H₂S</span></td>
                    <td><span class="num">{{ $gas['week_avgs']['o2'] }}</span><span class="lbl">Week avg O₂</span></td>
                    <td><span class="num">{{ $gas['week_avgs']['co'] }}</span><span class="lbl">Week avg CO</span></td>
                    <td><span class="num">{{ $gas['week_avgs']['co2'] }}</span><span class="lbl">Week avg CO₂</span></td>
                </tr>
            </table>
            @if($gas['by_gas'] !== [])
                <p style="margin:0 0 6px;">
                    @foreach($gas['by_gas'] as $pill)
                        <span class="pill pill-crit">{{ $pill['gas'] }} · {{ $pill['count'] }}</span>&nbsp;
                    @endforeach
                </p>
            @endif
            <h3>Daily readings (min · avg · max)</h3>
            @if($gas['readings_empty'])
                <p class="empty">{{ $gas['readings_empty'] }}</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Day</th><th>LEL %</th><th>H₂S ppm</th><th>O₂ %</th><th>CO ppm</th><th>CO₂ ppm</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($gas['readings'] as $row)
                        <tr>
                            <td class="day">{{ $row['date'] }}</td>
                            <td class="num">{{ $row['lel'] }}</td>
                            <td class="num">{{ $row['h2s'] }}</td>
                            <td class="num">{{ $row['o2'] }}</td>
                            <td class="num">{{ $row['co'] }}</td>
                            <td class="num">{{ $row['co2'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            <h3>Alarm events ({{ $gas['alarm_count'] }})</h3>
            @if($gas['alarms_empty'])
                <p class="empty">{{ $gas['alarms_empty'] }}<span class="hint">{{ $gas['alarms_empty_hint'] }}</span></p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Triggered</th><th>Device</th><th>Gas</th><th>Level</th>
                            <th>Peak</th><th>Duration</th><th>Acknowledged by</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($gas['alarms'] as $row)
                        <tr>
                            <td>{{ $row['when'] }}</td>
                            <td>{{ $row['device'] }}</td>
                            <td>{{ $row['gas'] }}</td>
                            <td><span class="{{ $severityPill($row['level']) }}">{{ $row['level'] }}</span></td>
                            <td class="num">{{ $row['peak'] }}</td>
                            <td class="num">{{ $row['duration'] }}</td>
                            <td>{{ $row['ack'] }}</td>
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
    @if($incidents['rows'] === [])
        <p class="empty">None.</p>
    @else
        @foreach($incidents['rows'] as $row)
            <div class="appendix-box">
                <strong>{{ $row['number'] }}</strong>
                <span class="{{ $severityPill($row['severity']) }}">{{ $row['severity'] }}</span>
                <br>Immediate: {{ $row['immediate'] }}
                <br>Corrective: {{ $row['corrective'] }}
            </div>
        @endforeach
    @endif

    <h3>LSR</h3>
    @if($lsr['rows'] === [])
        <p class="empty">None.</p>
    @else
        @foreach($lsr['rows'] as $row)
            <div class="appendix-box">
                <strong>{{ $row['category'] }}</strong> · {{ $row['zone'] }}
                <br>{{ $row['action'] }}
            </div>
        @endforeach
    @endif

    <h3>Vehicle violations</h3>
    @if($vehicles['rows'] === [])
        <p class="empty">None.</p>
    @else
        @foreach($vehicles['rows'] as $row)
            <div class="appendix-box">
                <strong>{{ $row['vehicle'] }}</strong>
                <span class="pill pill-warn">{{ $row['type'] }}</span>
                <br>{{ $row['action'] }}
            </div>
        @endforeach
    @endif

    <div class="footer-note">
        IR4 Weekly Report {{ $report->report_number }} · Data frozen at generation · {{ $periodStart }} → {{ $periodEnd }}
    </div>
</body>
</html>
