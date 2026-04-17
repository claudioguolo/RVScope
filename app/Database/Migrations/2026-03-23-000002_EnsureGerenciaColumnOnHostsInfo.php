<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureGerenciaColumnOnHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE hosts_info ADD COLUMN IF NOT EXISTS gerencia TEXT NOT NULL DEFAULT 'Sem registro'"
        );
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info') && $this->db->fieldExists('gerencia', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'gerencia');
        }
    }
}
