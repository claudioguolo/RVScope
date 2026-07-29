<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateHostRemovalReasonsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'reference_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'vm' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'updated_by' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => false,
                'default' => '',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey(['reference_date', 'vm'], true);
        $this->forge->addKey('vm', false, false, 'idx_host_removal_reasons_vm');
        $this->forge->createTable('host_removal_reasons', true);
    }

    public function down()
    {
        $this->forge->dropTable('host_removal_reasons', true);
    }
}
