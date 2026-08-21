<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCatalogPhoneAndActiveStatus extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('management_units')) {
            if (! $this->db->fieldExists('manager_phone', 'management_units')) {
                $this->forge->addColumn('management_units', [
                    'manager_phone' => [
                        'type' => 'VARCHAR',
                        'constraint' => 40,
                        'null' => false,
                        'default' => '',
                    ],
                ]);
            }
            if (! $this->db->fieldExists('is_active', 'management_units')) {
                $this->forge->addColumn('management_units', [
                    'is_active' => [
                        'type' => 'BOOLEAN',
                        'null' => false,
                        'default' => true,
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('technical_responsibles')
            && ! $this->db->fieldExists('is_active', 'technical_responsibles')) {
            $this->forge->addColumn('technical_responsibles', [
                'is_active' => [
                    'type' => 'BOOLEAN',
                    'null' => false,
                    'default' => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('technical_responsibles')
            && $this->db->fieldExists('is_active', 'technical_responsibles')) {
            $this->forge->dropColumn('technical_responsibles', 'is_active');
        }
        if ($this->db->tableExists('management_units')) {
            if ($this->db->fieldExists('is_active', 'management_units')) {
                $this->forge->dropColumn('management_units', 'is_active');
            }
            if ($this->db->fieldExists('manager_phone', 'management_units')) {
                $this->forge->dropColumn('management_units', 'manager_phone');
            }
        }
    }
}
