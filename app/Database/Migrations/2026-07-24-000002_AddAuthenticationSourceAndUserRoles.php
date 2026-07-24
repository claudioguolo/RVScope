<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuthenticationSourceAndUserRoles extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('admin_users')) {
            return;
        }

        if (! $this->db->fieldExists('auth_source', 'admin_users')) {
            $this->forge->addColumn('admin_users', [
                'auth_source' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => false,
                    'default' => 'local',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('admin_users')
            && $this->db->fieldExists('auth_source', 'admin_users')) {
            $this->forge->dropColumn('admin_users', 'auth_source');
        }
    }
}
