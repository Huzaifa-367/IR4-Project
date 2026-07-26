<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PPE Violations Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f3f3; }
        .note { margin-top: 16px; color: #555; }
    </style>
</head>
<body>
    <h1>PPE Violations</h1>
    <p>{{ $from->toDateString() }} — {{ $to->toDateString() }}</p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Camera</th>
                <th>Detected</th>
                <th>Count</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->violation_type->value }}</td>
                    <td>{{ $row->camera?->reference }}</td>
                    <td>{{ $row->detected_at->toDateTimeString() }}</td>
                    <td>{{ $row->worker_count }}</td>
                    <td>{{ $row->review_status->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="note">Excluded false positives: {{ $excluded }}</p>
</body>
</html>
