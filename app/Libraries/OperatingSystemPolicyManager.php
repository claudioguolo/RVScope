<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class OperatingSystemPolicyManager
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function list(): array
    {
        $this->synchronizeDetectedOperatingSystems();

        return $this->db->query(
            "SELECT policy.os_name,
                    policy.is_ignored,
                    COUNT(inventory.id) AS snapshot_count,
                    COUNT(DISTINCT inventory.vm) AS vm_count,
                    MAX(inventory.reference_date) AS last_seen
             FROM operating_system_policies policy
             LEFT JOIN rvtools_vm_inventory inventory
               ON inventory.os_name_raw = policy.os_name
             GROUP BY policy.os_name, policy.is_ignored
             ORDER BY LOWER(policy.os_name), policy.os_name"
        )->getResultArray();
    }

    public function update(array $ignoredOperatingSystems): void
    {
        $available = array_fill_keys(array_column($this->list(), 'os_name'), true);
        $selected = [];
        foreach ($ignoredOperatingSystems as $value) {
            $name = trim((string) $value);
            if ($name !== '' && isset($available[$name])) {
                $selected[$name] = true;
            }
        }

        $this->db->transBegin();
        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('operating_system_policies')->update([
                'is_ignored' => false,
                'updated_at' => $now,
            ]);
            if ($selected !== []) {
                $this->db->table('operating_system_policies')
                    ->whereIn('os_name', array_keys($selected))
                    ->update(['is_ignored' => true, 'updated_at' => $now]);
            }

            $this->rebuildInventoryAndSummaries();
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Database transaction failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function applyHostOverride(string $vm, string $operatingSystemOverride): void
    {
        $policyRows = $this->db->table('operating_system_policies')
            ->select('os_name, normalized_name, is_ignored')
            ->get()->getResultArray();
        $policies = [];
        $ignoredNormalized = [];
        foreach ($policyRows as $row) {
            $ignored = in_array($row['is_ignored'] ?? false, [true, 1, '1', 't', 'true'], true);
            $policies[(string) ($row['os_name'] ?? '')] = $ignored;
            if ($ignored && (string) ($row['normalized_name'] ?? '') !== '') {
                $ignoredNormalized[(string) $row['normalized_name']] = true;
            }
        }

        $normalizer = new OperatingSystemNameNormalizer((int) config('Rvtools')->osMaxLength);
        $inclusionPolicy = new OperatingSystemInclusionPolicy();
        $lastKnownOperatingSystem = '';
        $updates = [];
        $rows = $this->db->table('rvtools_vm_inventory')
            ->select('id, power_state, os_name_raw')
            ->where('vm', $vm)
            ->orderBy('reference_date', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $powerState = (string) ($row['power_state'] ?? '');
            $rawOperatingSystem = trim((string) ($row['os_name_raw'] ?? ''));
            $effectiveOperatingSystem = $operatingSystemOverride !== ''
                ? $operatingSystemOverride
                : $rawOperatingSystem;
            $normalized = $effectiveOperatingSystem !== ''
                && strcasecmp($effectiveOperatingSystem, 'nan') !== 0
                ? $normalizer->normalize($effectiveOperatingSystem)
                : '';
            $included = $inclusionPolicy->included(
                $powerState,
                $effectiveOperatingSystem,
                $normalized,
                $policies,
            );
            if ($operatingSystemOverride === ''
                && $powerState === 'poweredOn'
                && $normalized === ''
                && $lastKnownOperatingSystem !== ''
                && ! isset($ignoredNormalized[$lastKnownOperatingSystem])) {
                $normalized = $lastKnownOperatingSystem;
                $included = true;
            }
            if ($operatingSystemOverride === '' && $rawOperatingSystem !== '' && $included) {
                $lastKnownOperatingSystem = $normalized;
            }
            $updates[] = [
                'id' => (int) ($row['id'] ?? 0),
                'os_name' => $normalized,
                'included_in_reports' => $included,
            ];
        }
        if ($updates !== []) {
            $this->db->table('rvtools_vm_inventory')->updateBatch($updates, 'id');
        }
        $this->rebuildSummaries();
    }

    private function synchronizeDetectedOperatingSystems(): void
    {
        $this->db->query(
            "INSERT INTO operating_system_policies (os_name, normalized_name, is_ignored, created_at, updated_at)
             SELECT DISTINCT TRIM(os_name_raw),
                    CASE
                        WHEN os_name_raw LIKE 'CentOS%' THEN 'CentOS'
                        WHEN os_name_raw LIKE 'Other%' OR os_name_raw LIKE 'SUSE %' OR os_name_raw LIKE 'FreeB%' THEN 'Other'
                        ELSE LEFT(TRIM(REPLACE(os_name_raw, ' (64-bit)', '')), 27)
                    END,
                    FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM rvtools_vm_inventory
             WHERE TRIM(COALESCE(os_name_raw, '')) <> ''
               AND LOWER(TRIM(os_name_raw)) <> 'nan'
             ON CONFLICT (os_name) DO NOTHING"
        );
    }

    private function rebuildInventoryAndSummaries(): void
    {
        $this->db->query(
            "UPDATE rvtools_vm_inventory inventory
             SET os_name = policy.normalized_name
             FROM hosts_info info
             INNER JOIN operating_system_policies policy
               ON policy.os_name = info.operating_system_override
             WHERE info.vm = inventory.vm
               AND TRIM(COALESCE(info.operating_system_override, '')) <> ''"
        );
        $this->db->query(
            "UPDATE rvtools_vm_inventory inventory
             SET included_in_reports = (
                 inventory.power_state = 'poweredOn'
                 AND TRIM(COALESCE(inventory.os_name, '')) <> ''
                 AND NOT EXISTS (
                     SELECT 1
                     FROM operating_system_policies policy
                     WHERE (
                            policy.os_name = COALESCE(
                                NULLIF((
                                    SELECT info.operating_system_override
                                    FROM hosts_info info
                                    WHERE info.vm = inventory.vm
                                ), ''),
                                inventory.os_name_raw
                            )
                            OR (
                                TRIM(COALESCE(inventory.os_name_raw, '')) = ''
                                AND policy.normalized_name = inventory.os_name
                            )
                           )
                       AND policy.is_ignored = TRUE
                 )
             )"
        );
        $this->rebuildSummaries();
    }

    private function rebuildSummaries(): void
    {
        $this->db->table('rvtools_os_summary')->emptyTable();
        $this->db->query(
            "WITH available_dates AS (
                 SELECT reference_date,
                        LAG(reference_date) OVER (ORDER BY reference_date) AS previous_date
                 FROM (
                     SELECT DISTINCT reference_date
                     FROM rvtools_vm_inventory
                     WHERE included_in_reports = TRUE
                 ) dates
             ), summaries AS (
                 SELECT current_inventory.reference_date,
                        current_inventory.os_name,
                        COUNT(*) AS vm_count,
                        BOOL_OR(
                            available_dates.previous_date IS NOT NULL
                            AND previous_inventory.vm IS NULL
                        ) AS has_new
                 FROM rvtools_vm_inventory current_inventory
                 INNER JOIN available_dates
                   ON available_dates.reference_date = current_inventory.reference_date
                 LEFT JOIN rvtools_vm_inventory previous_inventory
                   ON previous_inventory.reference_date = available_dates.previous_date
                  AND previous_inventory.vm = current_inventory.vm
                  AND previous_inventory.included_in_reports = TRUE
                 WHERE current_inventory.included_in_reports = TRUE
                   AND TRIM(COALESCE(current_inventory.os_name, '')) <> ''
                 GROUP BY current_inventory.reference_date, current_inventory.os_name
             )
             INSERT INTO rvtools_os_summary (reference_date, os_name, vm_count, has_new)
             SELECT reference_date, os_name, vm_count, has_new
             FROM summaries
             ORDER BY reference_date, os_name"
        );
    }
}
