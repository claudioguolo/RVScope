<?php

namespace App\Libraries;

use InvalidArgumentException;

final class HostSecurityCollection
{
    public function validate(array $input): array
    {
        foreach (['hostname', 'sestatus', 'firewall'] as $field) {
            if (! isset($input[$field]) || ! is_string($input[$field])) {
                throw new InvalidArgumentException('Informe hostname, sestatus e firewall.');
            }
        }
        $hostname = strtolower(rtrim(trim($input['hostname']), '.'));
        if (strlen($hostname) > 253 || ! preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D', $hostname)) {
            throw new InvalidArgumentException('Hostname inválido.');
        }
        foreach (explode('.', $hostname) as $label) {
            if (strlen($label) > 63 || str_starts_with($label, '-') || str_ends_with($label, '-') || $label === '') {
                throw new InvalidArgumentException('Hostname inválido.');
            }
        }
        $selinux = strtolower(trim($input['sestatus']));
        $firewall = strtolower(trim($input['firewall']));
        if (! in_array($selinux, ['enforcing', 'permissive', 'disabled', 'unknown'], true)
            || ! in_array($firewall, ['active', 'inactive', 'failed', 'activating', 'deactivating', 'reloading', 'maintenance', 'refreshing', 'not-found', 'unknown'], true)) {
            throw new InvalidArgumentException('Estado de SELinux ou firewalld inválido. Use unknown quando não for possível consultar.');
        }
        return ['hostname' => $hostname, 'sestatus' => $selinux, 'firewall' => $firewall];
    }

    /** Não associa nomes curtos quando há mais de uma VM candidata. */
    public function report(array $records, array $inventory, string $term = ''): array
    {
        $index = [];
        foreach ($inventory as $position => $vm) {
            foreach (['vm', 'dns_name'] as $field) {
                $name = strtolower(rtrim(trim((string) ($vm[$field] ?? '')), '.'));
                if ($name !== '') {
                    $index[$name][$position] = true;
                    $index[explode('.', $name)[0]][$position] = true;
                }
            }
        }
        $rows = [];
        $unmatched = $ambiguous = $excluded = 0;
        foreach ($records as $record) {
            $matches = $index[$record['hostname']] ?? [];
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
            if (! isset($rows[$position]) || $rows[$position]['collection']['collected_at'] < $record['collected_at']) {
                $rows[$position] = $vm + ['collection' => $record];
            }
        }
        $rows = array_values(array_filter($rows, static fn ($row) => $term === '' || mb_stripos(
            implode(' ', [$row['vm'], $row['dns_name'] ?? '', $row['primary_ip'] ?? '', $row['collection']['hostname']]), $term
        ) !== false));
        usort($rows, static fn ($a, $b) => strcasecmp($a['vm'], $b['vm']));
        return compact('rows', 'unmatched', 'ambiguous', 'excluded');
    }
}
