<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateRvtoolsImportLogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'filename' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'reference_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'imported_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['filename'], 'rvtools_import_log_filename_unique');
        $this->forge->createTable('rvtools_import_log', true);
    }

    public function down()
    {
        $this->forge->dropTable('rvtools_import_log', true);
    }
}
