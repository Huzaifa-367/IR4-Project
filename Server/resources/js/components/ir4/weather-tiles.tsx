import { Link } from '@inertiajs/react';
import { StatusPill } from '@/components/ir4/status-pill';
import {
    ENVIRONMENT_METRICS,
    formatEnvironmentValue,
} from '@/lib/environment-metrics';
import { cn } from '@/lib/utils';
import environment from '@/routes/environment';
import type { DashboardSummary } from '@/types/dashboard';

type Props = {
    weather: NonNullable<DashboardSummary['weather']>;
    className?: string;
};

export function WeatherTiles({ weather, className }: Props) {
    return (
        <Link
            href={environment.index.url()}
            title="View environmental trends"
            className={cn(
                'inline-flex flex-wrap items-center gap-x-2.5 gap-y-1 rounded-pill border border-border bg-surface-2/90 px-2.5 py-1 text-[11px] transition-colors hover:border-[color:var(--accent)]/35 hover:bg-surface-2',
                className,
            )}
        >
            {ENVIRONMENT_METRICS.map((metric, index) => {
                const Icon = metric.icon;

                return (
                    <span key={metric.key} className="inline-flex items-center">
                        {index > 0 ? (
                            <span
                                aria-hidden
                                className="mr-2.5 hidden h-3 w-px bg-border sm:inline-block"
                            />
                        ) : null}
                        <span className="inline-flex items-center gap-1 font-mono text-text tabular-nums">
                            <Icon className="size-3 shrink-0 text-[color:var(--accent)]" />
                            {formatEnvironmentValue(
                                weather[metric.key],
                                metric.unit,
                            )}
                        </span>
                    </span>
                );
            })}
            {weather.stale ? (
                <>
                    <span
                        aria-hidden
                        className="hidden h-3 w-px bg-border sm:block"
                    />
                    <StatusPill label="Stale" tone="warn" />
                </>
            ) : null}
        </Link>
    );
}
