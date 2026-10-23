<?php

namespace App\Libraries;

final class ErrataReportBuilder
{
    public const LABELS = ['security' => 'Segurança', 'bugfix' => 'Correções', 'enhancement' => 'Melhorias', 'unspecified' => 'Não especificadas'];

    public function daily(array $records, ErrataReportCriteria $criteria): array
    {
        $days = [];
        foreach ($records as $record) {
            if ($criteria->includesDate($record['date'])) {
                $days[$record['date']][] = $record;
            }
        }
        krsort($days, SORT_STRING);
        foreach ($days as $date => $collections) {
            usort($collections, static fn ($left, $right) => strcmp($right['collected_at'], $left['collected_at']));
            $days[$date] = ['date' => $date, 'latest' => $collections[0], 'collections' => $collections];
        }
        return array_values($days);
    }

    /** Associação exata e sem ambiguidades; o filtro textual só é aplicado depois do vínculo. */
    public function hosts(array $records, array $inventory, string $date, string $term = '', int $page = 1): array
    {
        $index = [];
        foreach ($inventory as $position => $vm) {
            foreach ($this->inventoryKeys($vm) as $key) {
                $index[$key][$position] = true;
            }
        }
        $groups = [];
        foreach ($records as $record) {
            if ($record['date'] !== $date) {
                continue;
            }
            $identity = ErrataDataParser::hostIdentity($record);
            $groups[$identity][$record['collected_at']][] = $record;
        }
        $rows = $collisions = [];
        $unmatched = $excluded = $ambiguous = 0;
        foreach ($groups as $snapshots) {
            krsort($snapshots, SORT_STRING);
            $snapshot = reset($snapshots);
            $record = $snapshot[0];
            if ($record['format'] === 'items') {
                foreach ($snapshot as $itemRecord) {
                    if ($itemRecord['format'] !== 'items') {
                        ++$ambiguous;
                        continue 2;
                    }
                }
                $record = (new ErrataDataParser())->combineItems($snapshot) + array_intersect_key($record, array_flip(['host', 'hostname', 'ip']));
            } elseif (count($snapshot) > 1) {
                // Um CSV agregado deve conter apenas uma contagem por host e horário.
                ++$ambiguous;
                continue;
            }
            $matches = [];
            foreach ($this->hostKeys($record) as $key) {
                $matches += $index[$key] ?? [];
            }
            if (count($matches) !== 1) {
                count($matches) === 0 ? ++$unmatched : ++$ambiguous;
                continue;
            }
            $position = array_key_first($matches);
            $vm = $inventory[$position];
            if (($vm['power_state'] ?? '') !== 'poweredOn'
                || ! in_array($vm['included_in_reports'] ?? false, [true, 1, '1', 't', 'true'], true)) {
                ++$excluded;
                continue;
            }
            if (isset($collisions[$position])) {
                ++$ambiguous;
                continue;
            }
            if (isset($rows[$position])) {
                unset($rows[$position]);
                $collisions[$position] = true;
                $ambiguous += 2;
                continue;
            }
            $rows[$position] = $vm + ['satellite_host' => $record['host'] ?? '', 'satellite_hostname' => $record['hostname'] ?? '',
                'satellite_ip' => $record['ip'] ?? '', 'errata' => $record];
        }
        $rows = array_values(array_filter($rows, static function ($row) use ($term): bool {
            $text = implode(' ', array_map(static fn ($key) => (string) ($row[$key] ?? ''),
                ['vm', 'dns_name', 'primary_ip', 'satellite_host', 'satellite_hostname', 'satellite_ip']));
            return $term === '' || mb_stripos($text, $term) !== false;
        }));
        usort($rows, static fn ($left, $right) => strcasecmp($left['vm'], $right['vm']));
        $total = count($rows);
        $pages = max(1, (int) ceil($total / 50));
        $page = min(max(1, $page), $pages);
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($rows as $row) {
            foreach ($counts as $type => $value) {
                $counts[$type] += $row['errata']['counts'][$type];
            }
        }
        return ['rows' => array_slice($rows, ($page - 1) * 50, 50), 'total' => $total, 'pages' => $pages, 'page' => $page,
            'counts' => $counts, 'errata_total' => array_sum($counts), 'unmatched' => $unmatched, 'excluded' => $excluded, 'ambiguous' => $ambiguous];
    }

    private function inventoryKeys(array $vm): array
    {
        return array_values(array_unique(array_filter([
            $this->nameKey((string) ($vm['vm'] ?? '')),
            $this->nameKey((string) ($vm['dns_name'] ?? '')),
            $this->ipKey((string) ($vm['primary_ip'] ?? '')),
        ])));
    }

    private function hostKeys(array $host): array
    {
        return array_values(array_unique(array_filter([
            $this->nameKey((string) ($host['host'] ?? '')),
            $this->nameKey((string) ($host['hostname'] ?? '')),
            $this->ipKey((string) ($host['ip'] ?? '')),
        ])));
    }

    private function nameKey(string $value): string
    {
        $value = mb_strtolower(rtrim(trim($value), '.'));
        return $value === '' ? '' : 'name:' . $value;
    }

    private function ipKey(string $value): string
    {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_IP) ? 'ip:' . bin2hex(inet_pton($value)) : '';
    }
}
