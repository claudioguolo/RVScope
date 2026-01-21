<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateHostsInfoTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'vm' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'desc' => [
                'type' => 'TEXT',
                'null' => false,
                'default' => 'Sem registro',
            ],
            'owner' => [
                'type' => 'TEXT',
                'null' => false,
                'default' => 'Sem registro',
            ],
            'conv' => [
                'type' => 'TEXT',
                'null' => false,
                'default' => 'Nao informado',
            ],
            'leg' => [
                'type' => 'SMALLINT',
                'null' => false,
                'default' => 0,
            ],
            'mig' => [
                'type' => 'SMALLINT',
                'null' => false,
                'default' => 0,
            ],
            'app' => [
                'type' => 'SMALLINT',
                'null' => false,
                'default' => 0,
            ],
            'creation_date' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => false,
                'default' => '',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('vm', true);
        $this->forge->addKey('owner', false, false, 'idx_hosts_owner');
        $this->forge->createTable('hosts_info', true);
    }

    public function down()
    {
        $this->forge->dropTable('hosts_info', true);
    }
}
