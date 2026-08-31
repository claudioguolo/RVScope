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
             SET included_in_reports = (
                 inventory.power_state = 'poweredOn'
                 AND TRIM(COALESCE(inventory.os_name, '')) <> ''
                 AND NOT EXISTS (
                     SELECT 1
                     FROM operating_system_policies policy
                     WHERE (
                            policy.os_name = inventory.os_name_raw
                            OR (
                                TRIM(COALESCE(inventory.os_name_raw, '')) = ''
                                AND policy.normalized_name = inventory.os_name
                            )
                           )
                       AND policy.is_ignored = TRUE
                 )
             )"
        );
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
