<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRvtoolsInventoryAndHasNew extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('rvtools_os_summary')) {
            $fields = $this->db->getFieldNames('rvtools_os_summary');
            if (!in_array('has_new', $fields, true)) {
                $this->forge->addColumn('rvtools_os_summary', [
                    'has_new' => [
                        'type' => 'BOOLEAN',
                        'null' => false,
                        'default' => false,
                    ],
                ]);
            }
        }

        if (! $this->db->tableExists('rvtools_vm_inventory')) {
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
                'vm' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => false,
                ],
                'os_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => false,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('reference_date');
            $this->forge->addKey('vm');
            $this->forge->addUniqueKey(['reference_date', 'vm'], 'rvtools_vm_inventory_unique');
            $this->forge->createTable('rvtools_vm_inventory', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('rvtools_vm_inventory')) {
            $this->forge->dropTable('rvtools_vm_inventory', true);
        }

        if ($this->db->tableExists('rvtools_os_summary')) {
            $fields = $this->db->getFieldNames('rvtools_os_summary');
            if (in_array('has_new', $fields, true)) {
                $this->forge->dropColumn('rvtools_os_summary', 'has_new');
            }
        }
    }
}
