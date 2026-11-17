<?php

namespace App\Libraries;

/** Mantém somente vínculos únicos com VMs elegíveis no inventário da data consultada. */
final class HostSecurityInventoryLinker
{
    public function link(array $records, array $inventory): array
    {
        $index = [];
        foreach ($inventory as $position => $vm) {
            foreach (['vm', 'dns_name'] as $field) {
                $name = $this->name((string) ($vm[$field] ?? ''));
                if ($name !== '') {
                    $index['name:' . $name][$position] = true;
                    $index['name:' . explode('.', $name)[0]][$position] = true;
                }
            }
            $ip = $this->ip((string) ($vm['primary_ip'] ?? ''));
            if ($ip !== '') {
                $index['ip:' . $ip][$position] = true;
            }
        }
        $rows = [];
        foreach ($records as $record) {
            $matches = $index['name:' . $this->name($record['hostname'])] ?? [];
            $ip = $this->ip((string) ($record['ip'] ?? ''));
            if ($ip !== '') {
                $matches += $index['ip:' . $ip] ?? [];
            }
            if (count($matches) !== 1) {
                continue;
            }
            $position = array_key_first($matches);
            $vm = $inventory[$position];
            if (($vm['power_state'] ?? '') !== 'poweredOn'
                || ! in_array($vm['included_in_reports'] ?? false, [true, 1, '1', 't', 'true'], true)) {
                continue;
            }
            if (! isset($rows[$position]) || ($rows[$position]['collected_at'] ?? '') <= ($record['collected_at'] ?? '')) {
                $rows[$position] = $record + ['inventory_vm' => $vm['vm']];
            }
        }
        return array_values($rows);
    }

    private function name(string $value): string
    {
        return strtolower(rtrim(trim($value), '.'));
    }

    private function ip(string $value): string
    {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_IP) ? bin2hex(inet_pton($value)) : '';
    }
}
