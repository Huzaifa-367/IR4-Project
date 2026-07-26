<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $equipment['equipment_code'] }} — {{ $equipment['name'] }}</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #d7e0ea;
            --ok: #15803d;
            --warn: #d97706;
            --crit: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
        }
        header {
            padding: 16px 20px;
            background: #0b1220;
            color: #e8eef5;
            font-size: 14px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        main { padding: 20px; max-width: 640px; margin: 0 auto; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        h1 { margin: 0 0 8px; font-size: 28px; }
        h2 { margin: 0 0 12px; font-size: 16px; }
        .meta { color: var(--muted); font-size: 14px; }
        .badge {
            display: inline-block;
            margin-top: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #e8eef3;
        }
        .badge.retired { background: #fee2e2; color: var(--crit); }
        .badge.out { background: #ffedd5; color: var(--warn); }
        .badge.ok { background: #dcfce7; color: var(--ok); }
        .banner {
            background: #fee2e2;
            color: var(--crit);
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 8px; }
        a { color: #0284c8; }
    </style>
</head>
<body>
    <header>IR4 Equipment</header>
    <main>
        @if ($equipment['status'] === 'retired')
            <div class="banner">RETIRED — this item is out of the active fleet.</div>
        @endif

        <section class="card">
            <div class="meta">{{ $equipment['equipment_code'] }} · {{ $equipment['equipment_type'] }}</div>
            <h1>{{ $equipment['name'] }}</h1>
            <div class="meta">{{ $equipment['location_label'] ?: 'No location label' }}</div>
            <div class="badge {{ $equipment['status'] === 'retired' ? 'retired' : ($equipment['status'] === 'in_service' ? 'ok' : 'out') }}">
                {{ $equipment['status_label'] }}
            </div>
            <div class="badge {{ $equipment['checkout_state'] === 'available' ? 'ok' : 'out' }}" style="margin-left: 8px;">
                @if ($equipment['checkout_state'] === 'available')
                    Available
                @elseif (!empty($equipment['open_checkout']['worker']['name']))
                    Checked out to {{ $equipment['open_checkout']['worker']['name'] }}
                @else
                    Checked out
                @endif
            </div>
            @if ($equipment['description'])
                <p style="margin-top: 16px;">{{ $equipment['description'] }}</p>
            @endif
            <p class="meta" style="margin-top: 16px;">
                Next inspection: {{ $equipment['next_inspection_due'] ?: '—' }} ·
                Next service: {{ $equipment['next_service_due'] ?: '—' }}
            </p>
        </section>

        <section class="card">
            <h2>Inspection history</h2>
            @forelse ($inspections as $row)
                <div style="margin-bottom: 10px;">
                    <strong>{{ $row['inspected_at'] }}</strong> — {{ $row['outcome'] }}
                    @if ($row['notes'])
                        <div class="meta">{{ $row['notes'] }}</div>
                    @endif
                </div>
            @empty
                <div class="meta">No inspections recorded.</div>
            @endforelse
        </section>

        <section class="card">
            <h2>Maintenance history</h2>
            @forelse ($maintenances as $row)
                <div style="margin-bottom: 10px;">
                    <strong>{{ $row['performed_at'] }}</strong> — {{ $row['type'] }}
                    <div class="meta">{{ $row['description'] }}</div>
                </div>
            @empty
                <div class="meta">No maintenance recorded.</div>
            @endforelse
        </section>

        <section class="card">
            <h2>PM schedule</h2>
            @forelse ($schedules as $schedule)
                <div style="margin-bottom: 8px;">
                    {{ ucfirst(str_replace('_', ' ', $schedule['schedule_type'])) }} every {{ $schedule['interval_days'] }} days
                </div>
            @empty
                <div class="meta">No schedules configured.</div>
            @endforelse
        </section>

        <section class="card">
            <h2>Manuals / documents</h2>
            @forelse ($documents as $document)
                <div style="margin-bottom: 8px;">
                    <a href="{{ $document['url'] }}">{{ $document['title'] }}</a>
                </div>
            @empty
                <div class="meta">No documents attached.</div>
            @endforelse
        </section>
    </main>
</body>
</html>
