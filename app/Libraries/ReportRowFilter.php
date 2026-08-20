<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

final class ReportRowFilter
{
    public function __construct(private ?BaseConnection $db = null)
    {
    }

    public function normalizeManagementUnit(string $value): string
    {
        $value = trim($value);

        return $value === '' ? 'Sem registro' : $value;
    }

    public function summariesForDate(array $rows, string $date): array
    {
        if ($date === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (string) ($row['reference_date'] ?? '') === $date,
        ));
    }

    public function byManagementUnit(array $rows, string $managementUnit): array
    {
        $target = $this->lower($this->normalizeManagementUnit($managementUnit));

        return array_values(array_filter($rows, function (array $row) use ($target): bool {
            $rowManagementUnit = $this->normalizeManagementUnit(
                (string) ($row['info']['gerencia'] ?? ''),
            );

            return $this->lower($rowManagementUnit) === $target;
        }));
    }

    public function appliances(array $rows): array
    {
        return $this->byInfoFlag($rows, 'app');
    }

    public function legacy(array $rows): array
    {
        return $this->byInfoFlag($rows, 'leg');
    }

    public function migrable(array $rows): array
    {
        return $this->byInfoFlag($rows, 'mig');
    }

    public function presentInInventory(array $rows, string $date): array
    {
        if ($rows === []) {
            return [];
        }

        $db = $this->db ?? db_connect();
        $inventoryRows = $db->table('rvtools_vm_inventory')
            ->select('vm')
            ->where('reference_date', $date)
            ->where('included_in_reports', true)
            ->get()
            ->getResultArray();
        $allowed = [];
        foreach ($inventoryRows as $row) {
            $vm = trim((string) ($row['vm'] ?? ''));
            if ($vm !== '') {
                $allowed[$vm] = true;
            }
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => isset($allowed[trim((string) ($row['vm'] ?? ''))]),
        ));
    }

    private function byInfoFlag(array $rows, string $flag): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row['info'][$flag] ?? 0) === 1,
        ));
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
