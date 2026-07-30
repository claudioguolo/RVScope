<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMigrationTargetAndOsUpdateDateToHostsInfo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('migration_target', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'migration_target' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => false,
                    'default' => 'none',
                    'after' => 'mig',
                ],
            ]);
        }

        if (! $this->db->fieldExists('os_last_update_date', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'os_last_update_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'creation_date',
                ],
            ]);
        }

        $this->db->query(
            "UPDATE hosts_info
             SET migration_target = 'other_host'
             WHERE mig = 1
               AND (migration_target IS NULL OR migration_target = '' OR migration_target = 'none')"
        );
    }

    public function down()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if ($this->db->fieldExists('os_last_update_date', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'os_last_update_date');
        }
        if ($this->db->fieldExists('migration_target', 'hosts_info')) {
            $this->forge->dropColumn('hosts_info', 'migration_target');
        }
    }
}
