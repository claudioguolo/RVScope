<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOperatingSystemOverrideToHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('operating_system_override', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'operating_system_override' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                    'null' => true,
                ],
            ]);
        }

        $this->db->query(
            "DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_hosts_info_operating_system_override') THEN
                    ALTER TABLE hosts_info
                    ADD CONSTRAINT fk_hosts_info_operating_system_override
                    FOREIGN KEY (operating_system_override)
                    REFERENCES operating_system_policies(os_name)
                    ON UPDATE CASCADE ON DELETE SET NULL
                    NOT VALID;
                END IF;
            END \$\$"
        );
        $this->db->query('ALTER TABLE hosts_info VALIDATE CONSTRAINT fk_hosts_info_operating_system_override');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE hosts_info DROP CONSTRAINT IF EXISTS fk_hosts_info_operating_system_override');
        if ($this->db->fieldExists('operating_system_override', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'operating_system_override');
        }
    }
}
