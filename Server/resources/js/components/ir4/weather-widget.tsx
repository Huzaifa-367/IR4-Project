import { Link } from '@inertiajs/react';
import { CloudSun, Droplets, Wind } from 'lucide-react';
import { useState } from 'react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import type { EnvironmentSensor } from '@/types/environment';

export function WeatherWidget({
    initialSensors,
}: {
    initialSensors: EnvironmentSensor[];
}) {
    const [sensors, setSensors] = useState(initialSensors);
    const { status } = useReverbChannel({
        channel: 'environment',
        events: ['.EnvironmentUpdated'],
        onEvent: (payload: unknown) => {
            const event = payload as { sensor: EnvironmentSensor };
            setSensors((current) => [
                ...current.filter(
                    (sensor) => sensor.device_id !== event.sensor.device_id,
                ),
                event.sensor,
            ]);
        },
        snapshotUrl: '/api/environment/live',
        onSnapshot: (payload: unknown) => {
            const response = payload as {
                data: { sensors: EnvironmentSensor[] };
            };
            setSensors(response.data.sensors);
        },
        pollIntervalMs: 30_000,
    });
    const sensor = sensors[0];

    return (
        <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 className="font-medium">Site conditions</h2>
                    <p className="text-xs text-muted-foreground">
                        {sensor?.asset_label ??
                            sensor?.device_name ??
                            'No sensor registered'}
                    </p>
                </div>
                <LiveStatusPill status={status} />
            </div>
            {sensor ? (
                <>
                    <div className="grid grid-cols-3 gap-3">
                        <Metric
                            icon={CloudSun}
                            label="Temperature"
                            value={sensor.temperature_c}
                            unit="°C"
                        />
                        <Metric
                            icon={Droplets}
                            label="Humidity"
                            value={sensor.humidity_pct}
                            unit="%"
                        />
                        <Metric
                            icon={Wind}
                            label="Wind"
                            value={sensor.wind_speed_ms}
                            unit="m/s"
                        />
                    </div>
                    {Object.keys(sensor.extra).length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {Object.entries(sensor.extra).map(
                                ([key, value]) => (
                                    <span
                                        key={key}
                                        className="rounded bg-muted px-2 py-1 text-xs"
                                    >
                                        {key}: {value}
                                    </span>
                                ),
                            )}
                        </div>
                    )}
                    <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                        <span
                            className={sensor.is_stale ? 'text-amber-600' : ''}
                        >
                            {sensor.is_stale ? 'Stale' : 'Current'}
                            {sensor.recorded_at
                                ? ` · ${new Date(sensor.recorded_at).toLocaleString()}`
                                : ''}
                        </span>
                        <Link
                            href="/environment"
                            className="text-primary hover:underline"
                        >
                            Trends
                        </Link>
                    </div>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Waiting for environmental telemetry.
                </p>
            )}
        </section>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    unit,
}: {
    icon: typeof CloudSun;
    label: string;
    value: number | null;
    unit: string;
}) {
    return (
        <div className="rounded-lg bg-muted/50 p-3">
            <Icon className="mb-2 size-4 text-primary" />
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-xl font-semibold tabular-nums">
                {value ?? '—'}{' '}
                <span className="text-xs font-normal">
                    {value === null ? '' : unit}
                </span>
            </div>
        </div>
    );
}
