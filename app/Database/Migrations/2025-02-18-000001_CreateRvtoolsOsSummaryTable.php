<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRvtoolsOsSummaryTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'reference_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'os_name' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => false,
            ],
            'vm_count' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['reference_date', 'os_name'], 'rvtools_os_summary_unique');
        $this->forge->createTable('rvtools_os_summary', true);
    }

    public function down()
    {
        $this->forge->dropTable('rvtools_os_summary', true);
    }
}
