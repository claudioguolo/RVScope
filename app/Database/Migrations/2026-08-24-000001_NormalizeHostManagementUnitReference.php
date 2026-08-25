<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeHostManagementUnitReference extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')
            || ! $this->db->tableExists('management_units')
            || ! $this->db->fieldExists('gerencia', 'hosts_info')
            || ! $this->db->fieldExists('management_unit_id', 'hosts_info')) {
            return;
        }

        $this->db->query(
            "INSERT INTO management_units
                (name, department, manager_name, management_email, created_at, updated_at)
             SELECT MIN(TRIM(h.gerencia)), '', '', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM hosts_info h
             WHERE TRIM(h.gerencia) <> ''
               AND LOWER(TRIM(h.gerencia)) <> 'sem registro'
               AND NOT EXISTS (
                   SELECT 1
                   FROM management_units m
                   WHERE LOWER(TRIM(m.name)) = LOWER(TRIM(h.gerencia))
               )
             GROUP BY LOWER(TRIM(h.gerencia))"
        );

        $this->db->query(
            'UPDATE hosts_info h
             SET management_unit_id = (
                 SELECT MIN(m.id)
                 FROM management_units m
                 WHERE LOWER(TRIM(m.name)) = LOWER(TRIM(h.gerencia))
             )
             WHERE h.management_unit_id IS NULL
               AND TRIM(h.gerencia) <> \'\'
               AND LOWER(TRIM(h.gerencia)) <> \'sem registro\'
               AND EXISTS (
                   SELECT 1
                   FROM management_units m
                   WHERE LOWER(TRIM(m.name)) = LOWER(TRIM(h.gerencia))
               )'
        );

        $this->forge->dropColumn('hosts_info', 'gerencia');
    }

    public function down()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('gerencia', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'gerencia' => [
                    'type' => 'TEXT',
                    'null' => false,
                    'default' => 'Sem registro',
                ],
            ]);
        }

        if ($this->db->tableExists('management_units')
            && $this->db->fieldExists('management_unit_id', 'hosts_info')) {
            $this->db->query(
                "UPDATE hosts_info h
                 SET gerencia = COALESCE(NULLIF(TRIM(m.name), ''), 'Sem registro')
                 FROM management_units m
                 WHERE m.id = h.management_unit_id"
            );
        }
    }
}
