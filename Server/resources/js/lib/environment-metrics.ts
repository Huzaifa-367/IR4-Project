import { CloudSun, Droplets, Wind  } from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import type { EnvironmentSensor } from '@/types/environment';

export const ENVIRONMENT_METRICS = [
    {
        key: 'temperature_c',
        label: 'Temperature',
        unit: '°C',
        icon: CloudSun,
    },
    {
        key: 'humidity_pct',
        label: 'Humidity',
        unit: '%',
        icon: Droplets,
    },
    {
        key: 'wind_speed_ms',
        label: 'Wind speed',
        unit: ' m/s',
        icon: Wind,
    },
] as const satisfies ReadonlyArray<{
    key: keyof Pick<
        EnvironmentSensor,
        'temperature_c' | 'humidity_pct' | 'wind_speed_ms'
    >;
    label: string;
    unit: string;
    icon: LucideIcon;
}>;

export function formatEnvironmentValue(
    value: number | null,
    unit: string,
): string {
    return value === null ? '—' : `${value}${unit}`;
}
