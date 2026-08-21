<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSoftDeleteToManagementUnits extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('management_units')
            && ! $this->db->fieldExists('is_deleted', 'management_units')) {
            $this->forge->addColumn('management_units', [
                'is_deleted' => [
                    'type' => 'BOOLEAN',
                    'null' => false,
                    'default' => false,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('management_units')
            && $this->db->fieldExists('is_deleted', 'management_units')) {
            $this->forge->dropColumn('management_units', 'is_deleted');
        }
    }
}
