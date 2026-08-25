<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeHostTechnicalResponsibleReference extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hosts_info')
            || ! $this->db->tableExists('technical_responsibles')
            || ! $this->db->fieldExists('owner', 'hosts_info')
            || ! $this->db->fieldExists('technical_responsible_id', 'hosts_info')) {
            return;
        }

        $this->db->query(
            "INSERT INTO technical_responsibles
                (name, phone, email, created_at, updated_at)
             SELECT MIN(TRIM(h.owner)), '', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM hosts_info h
             WHERE TRIM(h.owner) <> ''
               AND LOWER(TRIM(h.owner)) NOT IN ('sem registro', 'nao informado', 'não informado')
               AND NOT EXISTS (
                   SELECT 1
                   FROM technical_responsibles r
                   WHERE LOWER(TRIM(r.name)) = LOWER(TRIM(h.owner))
               )
             GROUP BY LOWER(TRIM(h.owner))"
        );

        if ($this->db->tableExists('management_unit_technical_responsibles')
            && $this->db->fieldExists('management_unit_id', 'hosts_info')) {
            $this->db->query(
                "INSERT INTO management_unit_technical_responsibles
                    (management_unit_id, technical_responsible_id)
                 SELECT DISTINCT h.management_unit_id, r.id
                 FROM hosts_info h
                 JOIN technical_responsibles r
                   ON r.id = (
                       SELECT MIN(r2.id)
                       FROM technical_responsibles r2
                       WHERE LOWER(TRIM(r2.name)) = LOWER(TRIM(h.owner))
                   )
                 WHERE h.management_unit_id IS NOT NULL
                   AND TRIM(h.owner) <> ''
                   AND LOWER(TRIM(h.owner)) NOT IN ('sem registro', 'nao informado', 'não informado')
                 ON CONFLICT (management_unit_id, technical_responsible_id) DO NOTHING"
            );
        }

        $this->db->query(
            "UPDATE hosts_info h
             SET technical_responsible_id = (
                 SELECT MIN(r.id)
                 FROM technical_responsibles r
                 WHERE LOWER(TRIM(r.name)) = LOWER(TRIM(h.owner))
             )
             WHERE h.technical_responsible_id IS NULL
               AND TRIM(h.owner) <> ''
               AND LOWER(TRIM(h.owner)) NOT IN ('sem registro', 'nao informado', 'não informado')
               AND EXISTS (
                   SELECT 1
                   FROM technical_responsibles r
                   WHERE LOWER(TRIM(r.name)) = LOWER(TRIM(h.owner))
               )"
        );

        $this->forge->dropColumn('hosts_info', 'owner');
    }

    public function down()
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        if (! $this->db->fieldExists('owner', 'hosts_info')) {
            $this->forge->addColumn('hosts_info', [
                'owner' => [
                    'type' => 'TEXT',
                    'null' => false,
                    'default' => 'Sem registro',
                ],
            ]);
        }

        if ($this->db->tableExists('technical_responsibles')
            && $this->db->fieldExists('technical_responsible_id', 'hosts_info')) {
            $this->db->query(
                "UPDATE hosts_info h
                 SET owner = COALESCE(NULLIF(TRIM(r.name), ''), 'Sem registro')
                 FROM technical_responsibles r
                 WHERE r.id = h.technical_responsible_id"
            );
        }
    }
}
