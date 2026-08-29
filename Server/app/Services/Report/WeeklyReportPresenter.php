<?php

namespace App\Services\Report;

use App\Models\WeeklyReport;
use Illuminate\Support\Carbon;

/**
 * Shared presentation for weekly report web / PDF / CSV — keep these surfaces aligned.
 *
 * @phpstan-type SummaryTile array{key: string, label: string, value: string, detail: string, tone: string, has_gap: bool}
 * @phpstan-type SectionDef array{key: string, title: string, short: string, blurb: string}
 */
final class WeeklyReportPresenter
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $badges
     */
    public function __construct(
        public readonly WeeklyReport $report,
        public readonly array $data,
        public readonly array $badges,
    ) {}

    /**
     * @param  array<string, string>  $badges
     */
    public static function for(WeeklyReport $report, array $badges): self
    {
        return new self($report, $report->data ?? [], $badges);
    }

    /**
     * @return list<SectionDef>
     */
    public function sections(): array
    {
        return [
            [
                'key' => 'i_daily_safety_observations',
                'title' => 'i. Daily Safety Observations',
                'short' => 'Safety observations',
                'blurb' => 'Confirmed PPE detections by day and camera.',
            ],
            [
                'key' => 'ii_hse_incidents',
                'title' => 'ii. HSE Accidents & Incidents',
                'short' => 'HSE incidents',
                'blurb' => 'Operator-logged accidents and incidents for this week, with actions taken.',
            ],
            [
                'key' => 'iii_lsr_violations',
                'title' => 'iii. LSR Violations & Actions Taken',
                'short' => 'LSR violations',
                'blurb' => 'Life-Saving Rule breaches and the corrective action recorded for each.',
            ],
            [
                'key' => 'iv_weather',
                'title' => 'iv. Weather Conditions',
                'short' => 'Weather',
                'blurb' => 'Daily temperature and humidity from the site weather feed.',
            ],
            [
                'key' => 'v_manpower',
                'title' => 'v. Site Manpower',
                'short' => 'Manpower',
                'blurb' => 'Peak and average people on site for each day.',
            ],
            [
                'key' => 'vi_units_monitored',
                'title' => 'vi. Total Vehicles/Units Monitored',
                'short' => 'Units monitored',
                'blurb' => 'How many field poles/units were actively monitored this week.',
            ],
            [
                'key' => 'vii_vehicle_violations',
                'title' => 'vii. Vehicle Violations & Actions Taken',
                'short' => 'Vehicle violations',
                'blurb' => 'Manually logged vehicle violations and follow-up actions.',
            ],
            [
                'key' => 'ix_gas',
                // DOC-15 item viii (environmental) not shipped yet — show gas as viii so roman order stays contiguous.
                'title' => 'viii. Gas Monitoring (LEL / H₂S / O₂ / CO / CO₂)',
                'short' => 'Gas',
                'blurb' => 'Daily gas channel ranges and alarm events (alarm level only). Cells show min / avg / max.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function executiveLines(): array
    {
        $lines = [];
        $ppeDays = $this->data['i_daily_safety_observations']['per_day'] ?? [];
        $ppeTotal = (int) collect($ppeDays)->sum('total');
        $incidents = $this->data['ii_hse_incidents'] ?? [];
        $lsr = $this->data['iii_lsr_violations']['entries'] ?? [];
        $alarms = collect($this->data['ix_gas']['alarm_events'] ?? [])
            ->reject(fn ($raw): bool => str_contains(strtolower((string) ($raw['level'] ?? '')), 'warn'))
            ->values()
            ->all();
        $vehicles = $this->data['vii_vehicle_violations'] ?? [];
        $gapKeys = $this->gapItemKeys();
        $manpowerDays = $this->data['v_manpower']['per_day'] ?? [];
        $peakManpower = collect($manpowerDays)->max('peak');
        $weatherHasData = collect($this->data['iv_weather']['per_day'] ?? [])
            ->contains(fn ($day): bool => is_numeric($day['temp']['avg'] ?? null));

        if ($ppeTotal > 0) {
            $lines[] = $this->pluralize($ppeTotal, 'confirmed PPE observation').'.';
        } else {
            $lines[] = 'No confirmed PPE observations this week.';
        }

        $lines[] = $this->pluralize(count($incidents), 'HSE incident').', '
            .$this->pluralize(count($lsr), 'LSR violation').', and '
            .$this->pluralize(count($vehicles), 'vehicle violation').' logged by operators.';

        $lines[] = count($alarms) > 0
            ? $this->pluralize(count($alarms), 'gas alarm event').' — see Gas Monitoring for details.'
            : 'No gas alarm events in this period.';

        if (is_numeric($peakManpower) && (float) $peakManpower > 0) {
            $lines[] = 'Peak site manpower reached '.$this->fmt($peakManpower, 0).'.';
        } else {
            $lines[] = 'Site headcount stayed at zero — check coverage notes under Manpower.';
        }

        if (! $weatherHasData) {
            $lines[] = 'Weather samples were unavailable for this week.';
        }

        if ($gapKeys !== []) {
            $labels = collect($this->sections())
                ->filter(fn (array $s): bool => in_array($s['key'], $gapKeys, true))
                ->pluck('short')
                ->all();
            $lines[] = 'Sensor coverage gaps on '.implode(', ', $labels).' — figures for those items may be incomplete.';
        }

        return $lines;
    }

    /**
     * @return list<SummaryTile>
     */
    public function summaryTiles(): array
    {
        $gapKeys = $this->gapItemKeys();
        $ppeDays = $this->data['i_daily_safety_observations']['per_day'] ?? [];
        $ppeTotal = (int) collect($ppeDays)->sum('total');
        $ppeTypes = $this->mergeCounts(array_map(fn ($d) => $d['by_type'] ?? [], $ppeDays));

        $incidents = $this->data['ii_hse_incidents'] ?? [];
        $incidentSeverities = [];
        foreach ($incidents as $row) {
            $sev = (string) ($row['severity'] ?? '');
            if ($sev !== '') {
                $incidentSeverities[$sev] = ($incidentSeverities[$sev] ?? 0) + 1;
            }
        }

        $lsrEntries = $this->data['iii_lsr_violations']['entries'] ?? [];
        $lsrCats = collect($this->data['iii_lsr_violations']['summary_by_category'] ?? [])
            ->map(fn ($r) => $this->labelize($r['category'] ?? '').' ('.($r['count'] ?? 0).')')
            ->implode(', ');

        $weatherDays = $this->data['iv_weather']['per_day'] ?? [];
        $weatherTemps = collect($weatherDays)->map(fn ($d) => $d['temp']['avg'] ?? null)->filter(fn ($v) => is_numeric($v));
        $weatherHumidity = collect($weatherDays)->map(fn ($d) => $d['humidity']['avg'] ?? null)->filter(fn ($v) => is_numeric($v));
        $weatherHasData = $weatherTemps->isNotEmpty();

        $manpowerDays = $this->data['v_manpower']['per_day'] ?? [];
        $peakManpower = collect($manpowerDays)->max('peak');
        $avgManpower = collect($manpowerDays)->avg('average');
        $manpowerActive = ((float) ($peakManpower ?? 0)) > 0
            || collect($manpowerDays)->sum(fn ($d) => (float) ($d['entries'] ?? 0) + (float) ($d['samples'] ?? 0)) > 0;

        $units = (int) ($this->data['vi_units_monitored']['count'] ?? 0);
        $vehicles = $this->data['vii_vehicle_violations'] ?? [];
        $vehicleTypes = [];
        foreach ($vehicles as $row) {
            $type = (string) ($row['violation_type'] ?? '');
            if ($type !== '') {
                $vehicleTypes[$type] = ($vehicleTypes[$type] ?? 0) + 1;
            }
        }

        $gasAlarms = collect($this->data['ix_gas']['alarm_events'] ?? [])
            ->reject(fn ($raw): bool => str_contains(strtolower((string) ($raw['level'] ?? '')), 'warn'))
            ->values()
            ->all();
        $gasDetail = [
            'LEL '.$this->fmt($this->gasChannelAvg('lel')).'%',
            'H₂S '.$this->fmt($this->gasChannelAvg('h2s')),
            'O₂ '.$this->fmt($this->gasChannelAvg('o2')).'%',
        ];

        $tiles = [
            [
                'key' => 'i_daily_safety_observations',
                'label' => 'i. Safety observations',
                'value' => (string) $ppeTotal,
                'detail' => trim(implode(' · ', array_filter([
                    $this->byTypeLine($ppeTypes, 2) !== '—' ? $this->byTypeLine($ppeTypes, 2) : null,
                ]))) ?: 'No confirmed events',
                'tone' => $ppeTotal > 0 ? 'warn' : 'ok',
            ],
            [
                'key' => 'ii_hse_incidents',
                'label' => 'ii. HSE incidents',
                'value' => (string) count($incidents),
                'detail' => count($incidents) === 0 ? 'None logged' : $this->byTypeLine($incidentSeverities, 3),
                'tone' => count($incidents) > 0 ? 'crit' : 'ok',
            ],
            [
                'key' => 'iii_lsr_violations',
                'label' => 'iii. LSR violations',
                'value' => (string) count($lsrEntries),
                'detail' => $lsrCats !== '' ? $lsrCats : 'None logged',
                'tone' => count($lsrEntries) > 0 ? 'warn' : 'ok',
            ],
            [
                'key' => 'iv_weather',
                'label' => 'iv. Weather',
                'value' => $weatherHasData ? $this->fmt($weatherTemps->avg()).' °C' : 'No data',
                'detail' => $weatherHasData
                    ? 'Avg RH '.$this->fmt($weatherHumidity->avg(), 0).'%'
                    : 'Weather feed empty',
                'tone' => $weatherHasData ? 'neutral' : 'warn',
            ],
            [
                'key' => 'v_manpower',
                'label' => 'v. Manpower',
                'value' => $manpowerActive ? $this->fmt($peakManpower, 0) : '0',
                'detail' => $manpowerActive
                    ? 'Peak · avg '.$this->fmt($avgManpower, 0).'/day'
                    : 'No headcount this week',
                'tone' => $manpowerActive ? 'accent' : 'warn',
            ],
            [
                'key' => 'vi_units_monitored',
                'label' => 'vi. Units monitored',
                'value' => (string) $units,
                'detail' => 'Active field poles / units',
                'tone' => 'neutral',
            ],
            [
                'key' => 'vii_vehicle_violations',
                'label' => 'vii. Vehicle violations',
                'value' => (string) count($vehicles),
                'detail' => count($vehicles) === 0 ? 'None logged' : $this->byTypeLine($vehicleTypes, 2),
                'tone' => count($vehicles) > 0 ? 'warn' : 'ok',
            ],
            [
                'key' => 'ix_gas',
                'label' => 'viii. Gas monitoring',
                'value' => (string) count($gasAlarms),
                'detail' => (count($gasAlarms) > 0
                    ? $this->pluralize(count($gasAlarms), 'alarm').' · '
                    : 'No alarms · ')
                    .implode(' · ', $gasDetail),
                'tone' => count($gasAlarms) > 0 ? 'crit' : 'ok',
            ],
        ];

        foreach ($tiles as &$tile) {
            $tile['has_gap'] = in_array($tile['key'], $gapKeys, true);
            if ($tile['has_gap']) {
                $tile['detail'] .= ' · coverage gap';
                if ($tile['tone'] === 'ok') {
                    $tile['tone'] = 'warn';
                }
            }
        }
        unset($tile);

        return $tiles;
    }

    /**
     * One coverage banner per section (collapses legacy per-device floods).
     */
    public function coverageMessage(string $itemKey): ?string
    {
        $notes = array_values(array_filter(
            $this->data['completeness']['notes'] ?? [],
            fn ($n): bool => ($n['item'] ?? '') === $itemKey,
        ));

        if ($notes === []) {
            return null;
        }

        if (count($notes) === 1) {
            return (string) $notes[0]['message'];
        }

        return 'Coverage incomplete — some sensors for this item were offline more than 20% of the week. Treat figures with care.';
    }

    /**
     * @return list<string>
     */
    public function gapItemKeys(): array
    {
        $keys = [];
        foreach ($this->data['completeness']['notes'] ?? [] as $note) {
            $item = (string) ($note['item'] ?? '');
            if ($item !== '' && ! in_array($item, $keys, true)) {
                $keys[] = $item;
            }
        }

        return $keys;
    }

    /**
     * @return array{
     *   total: int,
     *   cameras_reporting: int,
     *   type_pills: list<array{label: string, count: int}>,
     *   per_day: list<array{date: string, total: int|string, types: string}>,
     *   by_camera: list<array{camera: string, total: int|string}>,
     *   empty_label: string|null,
     *   empty_hint: string|null
     * }
     */
    public function ppeSection(): array
    {
        $section = $this->data['i_daily_safety_observations'] ?? [];
        $perDayRaw = $section['per_day'] ?? [];
        $cameras = $section['by_camera'] ?? [];
        $total = (int) collect($perDayRaw)->sum('total');
        $types = $this->mergeCounts(array_map(fn ($d) => $d['by_type'] ?? [], $perDayRaw));
        arsort($types);

        $typePills = [];
        foreach ($types as $type => $count) {
            if ((int) $count > 0) {
                $typePills[] = ['label' => $this->labelize((string) $type), 'count' => (int) $count];
            }
        }

        $perDay = [];
        foreach ($perDayRaw as $row) {
            $perDay[] = [
                'date' => $this->formatDate($row['date'] ?? null),
                'total' => $row['total'] ?? 0,
                'types' => $this->byTypeLine($row['by_type'] ?? []),
            ];
        }

        $byCamera = [];
        foreach ($cameras as $row) {
            $byCamera[] = [
                'camera' => $this->cameraRefLabel($row['camera'] ?? null),
                'total' => $row['total'] ?? 0,
            ];
        }

        return [
            'total' => $total,
            'cameras_reporting' => count($cameras),
            'type_pills' => $typePills,
            'per_day' => $perDay,
            'by_camera' => $byCamera,
            'empty_label' => $perDay === [] ? 'No confirmed PPE observations this week.' : null,
            'empty_hint' => $perDay === [] ? 'No PPE detections were confirmed for this period.' : null,
        ];
    }

    /**
     * @return array{rows: list<array<string, string>>, empty_label: string|null, empty_hint: string|null}
     */
    public function incidentsSection(): array
    {
        $rows = [];
        foreach ($this->data['ii_hse_incidents'] ?? [] as $raw) {
            $rows[] = [
                'number' => $this->str($raw['incident_number'] ?? null),
                'when' => $this->formatDateTime($raw['occurred_at'] ?? null),
                'type' => $this->labelize($raw['type'] ?? null),
                'severity' => $this->labelize($raw['severity'] ?? null),
                'status' => $this->labelize($raw['status'] ?? null),
                'immediate' => $this->str($raw['immediate_action'] ?? null),
                'corrective' => $this->str($raw['corrective_action'] ?? null),
            ];
        }

        return [
            'rows' => $rows,
            'empty_label' => $rows === [] ? 'No HSE incidents logged this week.' : null,
            'empty_hint' => $rows === [] ? 'Incidents are created by operators — none were recorded for this period.' : null,
        ];
    }

    /**
     * @return array{
     *   summary: list<array{category: string, count: int}>,
     *   rows: list<array<string, string>>,
     *   empty_label: string|null,
     *   empty_hint: string|null
     * }
     */
    public function lsrSection(): array
    {
        $summary = [];
        foreach ($this->data['iii_lsr_violations']['summary_by_category'] ?? [] as $row) {
            $summary[] = [
                'category' => $this->labelize($row['category'] ?? null),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        $rows = [];
        foreach ($this->data['iii_lsr_violations']['entries'] ?? [] as $raw) {
            $rows[] = [
                'category' => $this->labelize($raw['category'] ?? null),
                'when' => $this->formatDateTime($raw['occurred_at'] ?? null),
                'worker' => $this->str($raw['worker'] ?? null),
                'zone' => $this->str($raw['zone'] ?? null),
                'action' => $this->str($raw['action_taken'] ?? null),
                'status' => $this->labelize($raw['status'] ?? null),
            ];
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
            'empty_label' => $rows === [] ? 'No LSR violations logged this week.' : null,
            'empty_hint' => $rows === [] ? 'Life-Saving Rule entries are operator-created.' : null,
        ];
    }

    /**
     * @return array{
     *   week_avg_temp: string,
     *   week_avg_humidity: string,
     *   rows: list<array{date: string, temp: string, humidity: string}>,
     *   empty_label: string|null,
     *   empty_hint: string|null
     * }
     */
    public function weatherSection(): array
    {
        $days = $this->data['iv_weather']['per_day'] ?? [];
        $rows = [];
        $temps = [];
        $humidities = [];

        foreach ($days as $raw) {
            $temp = is_array($raw['temp'] ?? null) ? $raw['temp'] : [];
            $humidity = is_array($raw['humidity'] ?? null) ? $raw['humidity'] : [];
            $hasTemp = $this->hasStats($temp);
            $hasHumidity = $this->hasStats($humidity);
            if (is_numeric($temp['avg'] ?? null)) {
                $temps[] = (float) $temp['avg'];
            }
            if (is_numeric($humidity['avg'] ?? null)) {
                $humidities[] = (float) $humidity['avg'];
            }
            if (! $hasTemp && ! $hasHumidity) {
                continue;
            }
            $rows[] = [
                'date' => $this->formatDate($raw['date'] ?? null),
                'temp' => $hasTemp ? $this->rangeLabel($temp) : 'No sample',
                'humidity' => $hasHumidity ? $this->rangeLabel($humidity) : 'No sample',
            ];
        }

        return [
            'week_avg_temp' => $this->fmt($temps === [] ? null : array_sum($temps) / count($temps)).' °C',
            'week_avg_humidity' => $this->fmt($humidities === [] ? null : array_sum($humidities) / count($humidities), 0).'%',
            'rows' => $rows,
            'empty_label' => $rows === [] ? 'No weather samples in this period.' : null,
            'empty_hint' => $rows === []
                ? 'The weather feed was empty or offline — check coverage notes if an outage was declared.'
                : null,
        ];
    }

    /**
     * @return array{
     *   rows: list<array<string, string>>,
     *   columns: list<string>,
     *   empty_label: string|null,
     *   empty_hint: string|null
     * }
     */
    public function manpowerSection(): array
    {
        $days = $this->data['v_manpower']['per_day'] ?? [];
        $hasMovement = false;
        foreach ($days as $raw) {
            if (((float) ($raw['peak'] ?? 0)) > 0
                || ((float) ($raw['entries'] ?? 0)) > 0
                || ((float) ($raw['exits'] ?? 0)) > 0
                || ((float) ($raw['samples'] ?? 0)) > 0) {
                $hasMovement = true;
                break;
            }
        }

        if (! $hasMovement) {
            return [
                'rows' => [],
                'columns' => ['date', 'peak', 'average'],
                'empty_label' => 'No headcount this week.',
                'empty_hint' => 'Peak and average stayed at zero. Check coverage notes under Data completeness.',
            ];
        }

        $rows = [];
        foreach ($days as $raw) {
            $rows[] = [
                'date' => $this->formatDate($raw['date'] ?? null),
                'peak' => $this->fmt($raw['peak'] ?? null, 0),
                'average' => $this->fmt($raw['average'] ?? null, 1),
            ];
        }

        return [
            'rows' => $rows,
            'columns' => ['date', 'peak', 'average'],
            'empty_label' => null,
            'empty_hint' => null,
        ];
    }

    /**
     * @return array{count: int, what: string, note: string}
     */
    public function unitsSection(): array
    {
        return [
            'count' => (int) ($this->data['vi_units_monitored']['count'] ?? 0),
            'what' => 'Poles / field assets with live monitoring devices — not fleet telematics',
            'note' => (string) ($this->data['vi_units_monitored']['note']
                ?? 'Active field units with monitoring devices'),
        ];
    }

    /**
     * @return array{rows: list<array<string, string>>, empty_label: string|null, empty_hint: string|null}
     */
    public function vehiclesSection(): array
    {
        $rows = [];
        foreach ($this->data['vii_vehicle_violations'] ?? [] as $raw) {
            $rows[] = [
                'when' => $this->formatDateTime($raw['observed_at'] ?? null),
                'vehicle' => $this->str($raw['vehicle_description'] ?? null),
                'type' => $this->labelize($raw['violation_type'] ?? null),
                'description' => $this->str($raw['description'] ?? null),
                'action' => $this->str($raw['action_taken'] ?? null),
                'by' => $this->str($raw['logged_by'] ?? null),
            ];
        }

        return [
            'rows' => $rows,
            'empty_label' => $rows === [] ? 'No vehicle violations logged this week.' : null,
            'empty_hint' => $rows === [] ? 'This item is entered manually by operators.' : null,
        ];
    }

    /**
     * @return array{
     *   alarm_count: int,
     *   during_outage: int,
     *   week_avgs: array{lel: string, h2s: string, o2: string, co: string, co2: string},
     *   by_gas: list<array{gas: string, count: int}>,
     *   readings: list<array<string, string>>,
     *   alarms: list<array<string, string>>,
     *   readings_empty: string|null,
     *   alarms_empty: string|null,
     *   alarms_empty_hint: string|null
     * }
     */
    public function gasSection(): array
    {
        $gasDays = $this->data['ix_gas']['per_day'] ?? [];
        $alarmRaw = $this->data['ix_gas']['alarm_events'] ?? [];

        $readings = [];
        foreach ($gasDays as $raw) {
            $readings[] = [
                'date' => $this->formatDateCompact($raw['date'] ?? null),
                'lel' => $this->mam($raw['lel'] ?? null),
                'h2s' => $this->mam($raw['h2s'] ?? null),
                'o2' => $this->mam($raw['o2'] ?? null),
                'co' => $this->mam($raw['co'] ?? null),
                'co2' => $this->mam($raw['co2'] ?? null, 0),
            ];
        }

        $alarms = [];
        $byGas = [];
        $duringOutage = 0;
        foreach ($alarmRaw as $raw) {
            $levelRaw = strtolower((string) ($raw['level'] ?? ''));
            if ($levelRaw === '' || str_contains($levelRaw, 'warn')) {
                continue;
            }
            if (! empty($raw['during_outage'])) {
                $duringOutage++;
            }
            $gas = $this->labelize($raw['gas'] ?? null);
            if ($gas !== '—') {
                $byGas[$gas] = ($byGas[$gas] ?? 0) + 1;
            }
            $alarms[] = [
                'when' => $this->formatDateTime($raw['triggered_at'] ?? null),
                'device' => $this->deviceRefLabel($raw['device'] ?? null),
                'gas' => $gas,
                'level' => $this->labelize($raw['level'] ?? null),
                'peak' => $this->fmt($raw['peak'] ?? null),
                'duration' => isset($raw['duration_s']) ? $raw['duration_s'].'s' : '—',
                'ack' => $this->str($raw['acknowledged_by'] ?? null),
            ];
        }
        arsort($byGas);
        $byGasList = [];
        foreach ($byGas as $gas => $count) {
            $byGasList[] = ['gas' => $gas, 'count' => $count];
        }

        $alarmCount = count($alarms);

        return [
            'alarm_count' => $alarmCount,
            'during_outage' => $duringOutage,
            'week_avgs' => [
                'lel' => $this->fmt($this->gasChannelAvg('lel')).'%',
                'h2s' => $this->fmt($this->gasChannelAvg('h2s')).' ppm',
                'o2' => $this->fmt($this->gasChannelAvg('o2')).'%',
                'co' => $this->fmt($this->gasChannelAvg('co')).' ppm',
                'co2' => $this->fmt($this->gasChannelAvg('co2'), 0).' ppm',
            ],
            'by_gas' => $byGasList,
            'readings' => $readings,
            'alarms' => $alarms,
            'readings_empty' => $readings === [] ? 'No daily gas readings in this period.' : null,
            'alarms_empty' => $alarms === [] ? 'No gas alarm events this week.' : null,
            'alarms_empty_hint' => $alarms === [] ? 'Channels stayed within thresholds for the period.' : null,
        ];
    }

    /**
     * Human-readable CSV files matching the web/PDF tables.
     *
     * @return array<string, string>
     */
    public function csvFiles(): array
    {
        $files = [
            '00_at_a_glance.csv' => $this->linesToCsv(
                [['line'], ...array_map(fn (string $l): array => [$l], $this->executiveLines())],
            ),
            '00_summary.csv' => $this->assocToCsv(
                [['item', 'value', 'detail', 'coverage_gap'], ...array_map(
                    fn (array $t): array => [$t['label'], $t['value'], $t['detail'], $t['has_gap'] ? 'yes' : 'no'],
                    $this->summaryTiles(),
                )],
            ),
        ];

        $ppe = $this->ppeSection();
        $files['i_daily_safety_observations.csv'] = $this->assocToCsv([
            ['date', 'total', 'by_type'],
            ...array_map(fn (array $r): array => [$r['date'], $r['total'], $r['types']], $ppe['per_day']),
        ], $ppe['empty_label']);
        $files['i_by_camera.csv'] = $this->assocToCsv([
            ['camera', 'total'],
            ...array_map(fn (array $r): array => [$r['camera'], $r['total']], $ppe['by_camera']),
        ], $ppe['by_camera'] === [] ? 'No cameras reporting' : null);

        $inc = $this->incidentsSection();
        $files['ii_hse_incidents.csv'] = $this->assocToCsv([
            ['number', 'occurred', 'type', 'severity', 'status', 'immediate_action', 'corrective_action'],
            ...array_map(fn (array $r): array => array_values($r), $inc['rows']),
        ], $inc['empty_label']);

        $lsr = $this->lsrSection();
        $files['iii_lsr_violations.csv'] = $this->assocToCsv([
            ['category', 'occurred', 'worker', 'zone', 'action_taken', 'status'],
            ...array_map(fn (array $r): array => array_values($r), $lsr['rows']),
        ], $lsr['empty_label']);

        $weather = $this->weatherSection();
        $files['iv_weather.csv'] = $this->assocToCsv([
            ['date', 'temp_min_avg_max_c', 'humidity_min_avg_max_pct'],
            ...array_map(fn (array $r): array => [$r['date'], $r['temp'], $r['humidity']], $weather['rows']),
        ], $weather['empty_label']);

        $manpower = $this->manpowerSection();
        $manpowerHeaders = $manpower['columns'] ?? ['date', 'peak', 'average', 'entries', 'exits'];
        $files['v_manpower.csv'] = $this->assocToCsv([
            $manpowerHeaders,
            ...array_map(fn (array $r): array => array_values($r), $manpower['rows']),
        ], $manpower['empty_label']);

        $units = $this->unitsSection();
        $files['vi_units_monitored.csv'] = $this->assocToCsv([
            ['active_units', 'what_this_counts', 'note'],
            [$units['count'], $units['what'], $units['note']],
        ]);

        $vehicles = $this->vehiclesSection();
        $files['vii_vehicle_violations.csv'] = $this->assocToCsv([
            ['observed', 'vehicle', 'type', 'description', 'action_taken', 'logged_by'],
            ...array_map(fn (array $r): array => array_values($r), $vehicles['rows']),
        ], $vehicles['empty_label']);

        $gas = $this->gasSection();
        $files['ix_gas_readings.csv'] = $this->assocToCsv([
            ['day', 'lel_pct', 'h2s_ppm', 'o2_pct', 'co_ppm', 'co2_ppm'],
            ...array_map(fn (array $r): array => array_values($r), $gas['readings']),
        ], $gas['readings_empty']);
        $files['ix_gas_alarms.csv'] = $this->assocToCsv([
            ['triggered', 'device', 'gas', 'level', 'peak', 'duration', 'acknowledged_by'],
            ...array_map(fn (array $r): array => array_values($r), $gas['alarms']),
        ], $gas['alarms_empty']);

        $files['meta.csv'] = $this->assocToCsv([
            ['report_number', 'period_start', 'period_end', 'status', 'generated_at', 'published_at'],
            [
                $this->report->report_number,
                $this->data['period']['start'] ?? $this->report->period_start,
                $this->data['period']['end'] ?? $this->report->period_end,
                $this->report->status->value,
                (string) $this->report->generated_at,
                (string) ($this->report->published_at ?? ''),
            ],
        ]);

        return $files;
    }

    private function gasChannelAvg(string $channel): ?float
    {
        $vals = collect($this->data['ix_gas']['per_day'] ?? [])
            ->map(fn ($d) => $d[$channel]['avg'] ?? null)
            ->filter(fn ($v) => is_numeric($v));

        return $vals->isEmpty() ? null : (float) $vals->avg();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function hasStats(array $stats): bool
    {
        return is_numeric($stats['min'] ?? null)
            || is_numeric($stats['avg'] ?? null)
            || is_numeric($stats['max'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function rangeLabel(array $stats): string
    {
        return $this->fmt($stats['min'] ?? null)
            .' – '.$this->fmt($stats['avg'] ?? null)
            .' – '.$this->fmt($stats['max'] ?? null);
    }

    /**
     * @param  array<string, mixed>|null  $stats
     */
    private function mam(?array $stats, int $digits = 1): string
    {
        if ($stats === null) {
            return '—';
        }

        return $this->fmt($stats['min'] ?? null, $digits)
            .' · '.$this->fmt($stats['avg'] ?? null, $digits)
            .' · '.$this->fmt($stats['max'] ?? null, $digits);
    }

    private function fmt(mixed $value, int $digits = 1): string
    {
        if ($value === null || $value === '' || (is_float($value) && is_nan($value))) {
            return '—';
        }

        if (is_int($value) || (is_numeric($value) && floor((float) $value) == $value)) {
            return (string) (int) $value;
        }

        return number_format((float) $value, $digits);
    }

    private function str(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }

    private function labelize(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }

        return ucwords(str_replace('_', ' ', (string) $value));
    }

    private function pluralize(int $count, string $singular, ?string $plural = null): string
    {
        $word = $count === 1 ? $singular : ($plural ?? $singular.'s');

        return $count.' '.$word;
    }

    /**
     * @param  array<string, int>  $byType
     */
    private function byTypeLine(array $byType, int $limit = 3): string
    {
        arsort($byType);
        $parts = [];
        foreach ($byType as $type => $count) {
            if ((int) $count <= 0) {
                continue;
            }
            $parts[] = $this->labelize((string) $type).' ('.$count.')';
            if (count($parts) >= $limit) {
                break;
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /**
     * @param  list<array<string, int>|mixed>  $maps
     * @return array<string, int>
     */
    private function mergeCounts(array $maps): array
    {
        $out = [];
        foreach ($maps as $map) {
            if (! is_array($map)) {
                continue;
            }
            foreach ($map as $key => $count) {
                $out[(string) $key] = ($out[(string) $key] ?? 0) + (int) $count;
            }
        }

        return $out;
    }

    private function cameraRefLabel(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '—';
        }
        if (preg_match('/^CAM-(FIXED|PTZ)-(\d{2})$/i', $raw, $m) === 1) {
            $kind = strtoupper($m[1]) === 'PTZ' ? 'PTZ' : 'Fixed';

            return 'Pole '.$m[2].' '.$kind.' Camera';
        }

        return $this->labelize($raw);
    }

    private function deviceRefLabel(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '—';
        }
        if (preg_match('/^CAM-(FIXED|PTZ)-(\d{2})$/i', $raw) === 1) {
            return $this->cameraRefLabel($raw);
        }
        if (preg_match('/^(?:DEV-)?GAS[-_]?(\d{2})$/i', $raw, $m) === 1) {
            return 'Pole '.$m[1].' Gas Detector';
        }
        if (preg_match('/^(?:DEV-)?RFID[-_]?(GATE|\d{2})$/i', $raw, $m) === 1) {
            $id = strtoupper($m[1]);

            return $id === 'GATE' ? 'Main Gate RFID Reader' : 'Pole '.$id.' RFID Reader';
        }

        return $this->labelize($raw);
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }
        try {
            return Carbon::parse((string) $value)->format('D, j M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatDateCompact(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }
        try {
            return Carbon::parse((string) $value)->format('D j M');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }
        try {
            return Carbon::parse((string) $value)->format('j M Y, H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * @param  list<list<string|int|float|null>>  $lines
     */
    private function linesToCsv(array $lines): string
    {
        return $this->assocToCsv($lines);
    }

    /**
     * @param  list<list<string|int|float|null>>  $lines
     */
    private function assocToCsv(array $lines, ?string $emptyMessage = null): string
    {
        if (count($lines) <= 1 && $emptyMessage !== null) {
            $lines = [$lines[0] ?? ['message'], [$emptyMessage]];
        }

        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        foreach ($lines as $line) {
            fputcsv($fh, $line, ',', '"', '\\');
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }
}
