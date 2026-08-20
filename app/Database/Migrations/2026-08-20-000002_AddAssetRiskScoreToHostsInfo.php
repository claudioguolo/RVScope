<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAssetRiskScoreToHostsInfo extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('hosts_info')
            && ! $this->db->fieldExists('asset_risk_score', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'asset_risk_score' => [
                    'type' => 'VARCHAR',
                    'constraint' => 160,
                    'null' => false,
                    'default' => '',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info')
            && $this->db->fieldExists('asset_risk_score', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'asset_risk_score');
        }
    }
}
