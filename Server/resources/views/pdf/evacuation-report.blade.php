<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Evacuation Report #{{ $report->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Evacuation Report #{{ $report->id }}</h1>
    <p>Triggered: {{ $report->triggered_at }} by {{ $report->triggerer?->name }}</p>
    <p>Status: {{ $report->status->value }}@if($report->force_closed) (force-closed)@endif</p>

    <h2>Unaccounted</h2>
    <table>
        <thead><tr><th>Worker</th><th>Last zone</th><th>Last seen</th></tr></thead>
        <tbody>
        @foreach($report->entries->where('muster_status', \App\Enums\MusterStatus::Unaccounted) as $entry)
            <tr>
                <td>{{ $entry->worker?->name ?? ('Worker #'.$entry->worker_id) }}</td>
                <td>{{ $entry->lastZone?->name ?? '—' }}</td>
                <td>{{ $entry->last_seen_at }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Accounted</h2>
    <table>
        <thead><tr><th>Worker</th><th>Source</th><th>Accounted at</th></tr></thead>
        <tbody>
        @foreach($report->entries->where('muster_status', \App\Enums\MusterStatus::Accounted) as $entry)
            <tr>
                <td>{{ $entry->worker?->name ?? ('Worker #'.$entry->worker_id) }}</td>
                <td>{{ $entry->accounted_source?->value ?? '—' }}</td>
                <td>{{ $entry->accounted_at }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
