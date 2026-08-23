import { CloudSun, Droplets, type LucideIcon } from 'lucide-react';
import type { EnvironmentSensor } from '@/types/environment';

/** Displayed ambient metrics (temperature + humidity). Wind is stored but not shown. */
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
] as const satisfies ReadonlyArray<{
    key: keyof Pick<EnvironmentSensor, 'temperature_c' | 'humidity_pct'>;
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
