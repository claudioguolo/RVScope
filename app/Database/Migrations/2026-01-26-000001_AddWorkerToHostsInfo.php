<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWorkerToHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('worker', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'worker' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => false,
                    'default' => 'none',
                    'after' => 'app',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info') && $this->db->fieldExists('worker', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'worker');
        }
    }
}
