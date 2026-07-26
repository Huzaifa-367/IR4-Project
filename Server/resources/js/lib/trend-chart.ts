type TrendPoint = {
    at: string;
    avg?: number | null;
    value?: number | null;
};

type TrendSeries = {
    key: string;
    label: string;
    unit?: string;
    points: TrendPoint[];
};

export function buildTrendChartData(
    series: TrendSeries[],
    range: 'day' | 'week' | 'custom',
): Array<Record<string, string | number | null>> {
    const byTime = new Map<string, Record<string, string | number | null>>();

    series.forEach((metricSeries) => {
        metricSeries.points.forEach((point) => {
            const date = new Date(point.at);
            const existing = byTime.get(point.at) ?? {
                at: point.at,
                label:
                    range === 'week' || range === 'custom'
                        ? date.toLocaleDateString(undefined, {
                              weekday: 'short',
                              month: 'short',
                              day: 'numeric',
                              hour:
                                  range === 'custom' ? '2-digit' : undefined,
                              minute:
                                  range === 'custom' ? '2-digit' : undefined,
                          })
                        : date.toLocaleTimeString(undefined, {
                              hour: '2-digit',
                              minute: '2-digit',
                          }),
            };
            existing[metricSeries.key] = point.avg ?? point.value ?? null;
            byTime.set(point.at, existing);
        });
    });

    return Array.from(byTime.values()).sort((a, b) =>
        String(a.at).localeCompare(String(b.at)),
    );
}

export function trendChartSeries(
    series: TrendSeries[],
): Array<{
    key: string;
    label: string;
    type: 'area' | 'line';
}> {
    return series.map((metric, index) => ({
        key: metric.key,
        label: metric.unit
            ? `${metric.label} (${metric.unit})`
            : metric.label,
        type: (index === 0 ? 'area' : 'line') as 'area' | 'line',
    }));
}
