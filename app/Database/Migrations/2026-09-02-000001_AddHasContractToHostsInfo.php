<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHasContractToHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('has_contract', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'has_contract' => [
                    'type' => 'BOOLEAN',
                    'null' => false,
                    'default' => false,
                ],
            ]);
        }

        $this->db->query(
            "UPDATE hosts_info
             SET has_contract = CASE WHEN BTRIM(COALESCE(contract, '')) <> '' THEN TRUE ELSE FALSE END"
        );

        $this->db->query(
            "DO \$\$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'chk_hosts_info_contract_presence'
                ) THEN
                    ALTER TABLE hosts_info
                    ADD CONSTRAINT chk_hosts_info_contract_presence
                    CHECK (
                        has_contract = FALSE
                        OR BTRIM(COALESCE(contract, '')) <> ''
                    ) NOT VALID;
                END IF;
            END \$\$"
        );
        $this->db->query('ALTER TABLE hosts_info VALIDATE CONSTRAINT chk_hosts_info_contract_presence');
    }

    public function down()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        $this->db->query('ALTER TABLE hosts_info DROP CONSTRAINT IF EXISTS chk_hosts_info_contract_presence');
        if ($this->db->fieldExists('has_contract', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'has_contract');
        }
    }
}
