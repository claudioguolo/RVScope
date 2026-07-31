<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddContractToHostsInfo extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('hosts_info') && ! $this->db->fieldExists('contract', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'contract' => [
                    'type' => 'TEXT',
                    'null' => false,
                    'default' => '',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info') && $this->db->fieldExists('contract', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'contract');
        }
    }
}
