<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report->report_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 18px; border-bottom: 1px solid #333; padding-bottom: 4px; }
        .badge { display: inline-block; background: #eee; border: 1px solid #999; padding: 2px 6px; font-size: 10px; }
        .note { background: #fff3cd; border: 1px solid #856404; padding: 8px; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .meta { margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>Weekly Report {{ $report->report_number }}</h1>
    <div class="meta">
        <div>Period: {{ $data['period']['start'] ?? '' }} → {{ $data['period']['end'] ?? '' }}</div>
        <div>Generated: {{ $report->generated_at }}</div>
        <div>Status: {{ $report->status->value }}</div>
        @if($report->supersedes)
            <div><strong>Supersedes {{ $report->supersedes->report_number }}</strong></div>
        @endif
    </div>

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
            'ix_gas' => 'ix. Gas Monitoring',
            'x_co2' => 'x. CO₂ Monitoring',
        ];
        $notes = collect($data['completeness']['notes'] ?? []);
    @endphp

    @foreach($sections as $key => $title)
        <h2>{{ $title }} <span class="badge">{{ $badges[$key] ?? '' }}</span></h2>
        @foreach($notes->where('item', $key) as $note)
            <div class="note">{{ $note['message'] }}</div>
        @endforeach

        @if($key === 'i_daily_safety_observations')
            <p>False positives excluded: {{ $data[$key]['false_positives_excluded'] ?? 0 }}</p>
            <table>
                <thead><tr><th>Date</th><th>Total</th><th>By type</th></tr></thead>
                <tbody>
                @foreach(($data[$key]['per_day'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ json_encode($row['by_type'] ?? []) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @elseif($key === 'ii_hse_incidents')
            <table>
                <thead><tr><th>Number</th><th>Type</th><th>Severity</th><th>Immediate action</th><th>Corrective action</th></tr></thead>
                <tbody>
                @foreach(($data[$key] ?? []) as $row)
                    <tr>
                        <td>{{ $row['incident_number'] }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td>{{ $row['severity'] }}</td>
                        <td>{{ $row['immediate_action'] }}</td>
                        <td>{{ $row['corrective_action'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @elseif($key === 'iii_lsr_violations')
            <table>
                <thead><tr><th>Category</th><th>Occurred</th><th>Worker</th><th>Action taken</th><th>Status</th></tr></thead>
                <tbody>
                @foreach(($data[$key]['entries'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['occurred_at'] }}</td>
                        <td>{{ $row['worker'] }}</td>
                        <td>{{ $row['action_taken'] }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @elseif($key === 'vi_units_monitored')
            <p>Count: {{ $data[$key]['count'] ?? 0 }}</p>
            <p>{{ $data[$key]['note'] ?? '' }}</p>
        @elseif($key === 'vii_vehicle_violations')
            <table>
                <thead><tr><th>Observed</th><th>Vehicle</th><th>Type</th><th>Action taken</th></tr></thead>
                <tbody>
                @foreach(($data[$key] ?? []) as $row)
                    <tr>
                        <td>{{ $row['observed_at'] }}</td>
                        <td>{{ $row['vehicle_description'] }}</td>
                        <td>{{ $row['violation_type'] }}</td>
                        <td>{{ $row['action_taken'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @elseif(in_array($key, ['iv_weather', 'v_manpower', 'viii_environmental', 'x_co2'], true))
            <table>
                <thead><tr><th>Date</th><th>Data</th></tr></thead>
                <tbody>
                @foreach(($data[$key]['per_day'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td><pre style="white-space:pre-wrap;font-size:9px;">{{ json_encode($row, JSON_PRETTY_PRINT) }}</pre></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @elseif($key === 'ix_gas')
            <table>
                <thead><tr><th>Date</th><th>Gas</th><th>Min</th><th>Avg</th><th>Max</th></tr></thead>
                <tbody>
                @foreach(($data[$key]['per_gas_per_day'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['gas'] }}</td>
                        <td>{{ $row['min'] }}</td>
                        <td>{{ $row['avg'] }}</td>
                        <td>{{ $row['max'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h2>Appendix — Action taken</h2>
    <h3>Incidents</h3>
    <ul>
        @foreach(($data['ii_hse_incidents'] ?? []) as $row)
            <li>{{ $row['incident_number'] }}: {{ $row['immediate_action'] }} / {{ $row['corrective_action'] }}</li>
        @endforeach
    </ul>
    <h3>LSR</h3>
    <ul>
        @foreach(($data['iii_lsr_violations']['entries'] ?? []) as $row)
            <li>{{ $row['category'] }}: {{ $row['action_taken'] }}</li>
        @endforeach
    </ul>
    <h3>Vehicle violations</h3>
    <ul>
        @foreach(($data['vii_vehicle_violations'] ?? []) as $row)
            <li>{{ $row['vehicle_description'] }}: {{ $row['action_taken'] }}</li>
        @endforeach
    </ul>
</body>
</html>
