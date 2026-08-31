<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperatingSystemPolicies extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('operating_system_policies')) {
            $this->forge->addField([
                'os_name' => ['type' => 'VARCHAR', 'constraint' => 500],
                'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 27],
                'is_ignored' => ['type' => 'BOOLEAN', 'default' => false],
                'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
                'updated_at' => ['type' => 'TIMESTAMP', 'null' => false],
            ]);
            $this->forge->addKey('os_name', true);
            $this->forge->createTable('operating_system_policies', true);
        } elseif (! $this->db->fieldExists('normalized_name', 'operating_system_policies')) {
            $this->forge->addColumn('operating_system_policies', [
                'normalized_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 27,
                    'default' => '',
                ],
            ]);
        }

        if ($this->db->tableExists('rvtools_vm_inventory')) {
            $this->db->query(
                'CREATE INDEX IF NOT EXISTS idx_rvtools_vm_inventory_os_name_raw
                 ON rvtools_vm_inventory (os_name_raw)'
            );
            $this->db->query(
                "INSERT INTO operating_system_policies (os_name, normalized_name, is_ignored, created_at, updated_at)
                 SELECT DISTINCT TRIM(os_name_raw),
                        CASE
                            WHEN os_name_raw LIKE 'CentOS%' THEN 'CentOS'
                            WHEN os_name_raw LIKE 'Other%' OR os_name_raw LIKE 'SUSE %' OR os_name_raw LIKE 'FreeB%' THEN 'Other'
                            ELSE LEFT(TRIM(REPLACE(os_name_raw, ' (64-bit)', '')), 27)
                        END,
                        CASE WHEN os_name_raw LIKE 'Microsoft%'
                               OR os_name_raw LIKE 'VMware%'
                               OR os_name_raw LIKE 'Forti%'
                             THEN TRUE ELSE FALSE END,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                 FROM rvtools_vm_inventory
                 WHERE TRIM(COALESCE(os_name_raw, '')) <> ''
                   AND LOWER(TRIM(os_name_raw)) <> 'nan'
                 ON CONFLICT (os_name) DO NOTHING"
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('rvtools_vm_inventory')) {
            $this->db->query('DROP INDEX IF EXISTS idx_rvtools_vm_inventory_os_name_raw');
        }
        $this->forge->dropTable('operating_system_policies', true);
    }
}
