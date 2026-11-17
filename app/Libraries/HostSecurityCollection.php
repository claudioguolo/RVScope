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
        $record = ['hostname' => $hostname, 'sestatus' => $selinux, 'firewall' => $firewall];
        if (array_key_exists('ip', $input)) {
            if (! is_string($input['ip']) || (trim($input['ip']) !== '' && ! filter_var(trim($input['ip']), FILTER_VALIDATE_IP))) {
                throw new InvalidArgumentException('Informe um único IP válido (IPv4 ou IPv6).');
            }
            $ip = trim($input['ip']);
            $record['ip'] = $ip === '' ? '' : inet_ntop(inet_pton($ip));
        }
        return $record;
    }

    /** Consulta exclusivamente os registros recebidos pela coleta de segurança. */
    public function report(array $records, string $term = ''): array
    {
        $rows = array_values(array_filter($this->latestPerDay($records), static fn ($record) => $term === ''
            || mb_stripos($record['hostname'] . ' ' . ($record['ip'] ?? ''), $term) !== false));
        usort($rows, static fn ($a, $b) => strcasecmp($a['hostname'], $b['hostname']));
        return ['rows' => $rows, 'summary' => [
            'sestatus' => $this->summarize($rows, 'sestatus', ['enforcing', 'permissive', 'disabled', 'unknown']),
            'firewall' => $this->summarize($rows, 'firewall', ['active', 'inactive', 'failed', 'unknown']),
        ]];
    }

    /** Agrupa vínculos por hostname ou IP no mesmo dia e mantém o registro mais recente. */
    private function latestPerDay(array $records): array
    {
        $parents = $identities = [];
        $find = static function (int $position) use (&$parents): int {
            while ($parents[$position] !== $position) {
                $parents[$position] = $parents[$parents[$position]];
                $position = $parents[$position];
            }
            return $position;
        };
        $records = array_values($records);
        foreach ($records as $position => $record) {
            $parents[$position] = $position;
            $day = substr($record['collected_at'] ?? '', 0, 10);
            $hostname = strtolower(rtrim(trim($record['hostname']), '.'));
            $keys = [$day . '|hostname:' . $hostname];
            $ip = trim($record['ip'] ?? '');
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $keys[] = $day . '|ip:' . bin2hex(inet_pton($ip));
            }
            foreach ($keys as $key) {
                if (isset($identities[$key])) {
                    $parents[$find($position)] = $find($identities[$key]);
                }
                $identities[$key] = $position;
            }
        }
        $latest = [];
        foreach ($records as $position => $record) {
            $group = $find($position);
            // Em empate de segundos, prevalece o último registro no arquivo.
            if (! isset($latest[$group]) || ($record['collected_at'] ?? '') >= ($latest[$group]['collected_at'] ?? '')) {
                $latest[$group] = $record;
            }
        }
        return array_values($latest);
    }

    /** Percentuais usam todos os hosts filtrados, antes da paginação. */
    private function summarize(array $rows, string $field, array $statuses): array
    {
        $counts = array_fill_keys($statuses, 0);
        foreach ($rows as $row) {
            $status = $row[$field];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $total = count($rows);
        $summary = [];
        foreach ($counts as $status => $count) {
            $summary[$status] = ['count' => $count, 'percentage' => $total === 0 ? 0.0 : 100.0 * $count / $total];
        }
        return $summary;
    }
}
