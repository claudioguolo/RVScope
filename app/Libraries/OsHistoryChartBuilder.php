<?php

namespace App\Libraries;

final class OsHistoryChartBuilder
{
    private const COLORS = [
        '#0d6efd', '#dc3545', '#198754', '#fd7e14', '#6f42c1',
        '#0dcaf0', '#d63384', '#6c757d', '#20c997', '#ffc107',
        '#6610f2', '#b02a37', '#146c43', '#ca6510', '#3d0a91',
    ];

    public function build(array $rows): array
    {
        $dates = [];
        $valuesByOs = [];

        foreach ($rows as $row) {
            $date = trim((string) ($row['reference_date'] ?? ''));
            $os = trim((string) ($row['os_name'] ?? ''));
            if ($date === '' || $os === '') {
                continue;
            }

            $dates[$date] = true;
            $valuesByOs[$os][$date] = max(0, (int) ($row['vm_count'] ?? 0));
        }

        $labels = array_keys($dates);
        sort($labels, SORT_STRING);
        uksort($valuesByOs, 'strcasecmp');

        $series = [];
        $maximum = 0;
        foreach ($valuesByOs as $os => $valuesByDate) {
            $values = [];
            foreach ($labels as $date) {
                $value = (int) ($valuesByDate[$date] ?? 0);
                $values[] = $value;
                $maximum = max($maximum, $value);
            }
            $series[] = [
                'name' => $os,
                'color' => self::COLORS[count($series) % count(self::COLORS)],
                'values' => $values,
            ];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'maximum' => $maximum,
        ];
    }
}
