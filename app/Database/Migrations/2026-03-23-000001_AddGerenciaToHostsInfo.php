<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGerenciaToHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('gerencia', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'gerencia' => [
                    'type' => 'TEXT',
                    'null' => false,
                    'default' => 'Sem registro',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info') && $this->db->fieldExists('gerencia', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'gerencia');
        }
    }
}
