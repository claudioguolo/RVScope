<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddContractValidityToHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('contract_valid_until', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'contract_valid_until' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
            ]);
        }

    }

    public function down()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if ($this->db->fieldExists('contract_valid_until', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'contract_valid_until');
        }
    }
}
