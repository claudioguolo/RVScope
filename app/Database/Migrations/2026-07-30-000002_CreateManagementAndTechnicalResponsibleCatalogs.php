<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateManagementAndTechnicalResponsibleCatalogs extends Migration
{
    public function up()
    {
        $this->createManagementUnitsTable();
        $this->createTechnicalResponsiblesTable();
        $this->createRelationshipTable();

        if ($this->db->tableExists('hosts_info')) {
            if (! $this->db->fieldExists('management_unit_id', 'hosts_info')) {
                $this->forge->addColumn('hosts_info', [
                    'management_unit_id' => [
                        'type' => 'INTEGER',
                        'null' => true,
                    ],
                ]);
            }
            if (! $this->db->fieldExists('technical_responsible_id', 'hosts_info')) {
                $this->forge->addColumn('hosts_info', [
                    'technical_responsible_id' => [
                        'type' => 'INTEGER',
                        'null' => true,
                    ],
                ]);
            }
        }

        $this->importExistingAssignments();
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info')) {
            if ($this->db->fieldExists('technical_responsible_id', 'hosts_info')) {
                $this->forge->dropColumn('hosts_info', 'technical_responsible_id');
            }
            if ($this->db->fieldExists('management_unit_id', 'hosts_info')) {
                $this->forge->dropColumn('hosts_info', 'management_unit_id');
            }
        }

        $this->forge->dropTable('management_unit_technical_responsibles', true);
        $this->forge->dropTable('technical_responsibles', true);
        $this->forge->dropTable('management_units', true);
    }

    private function createManagementUnitsTable(): void
    {
        if ($this->db->tableExists('management_units')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'SERIAL', 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => false],
            'department' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => false, 'default' => ''],
            'manager_name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => false, 'default' => ''],
            'management_email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => false, 'default' => ''],
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
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name', 'uq_management_units_name');
        $this->forge->createTable('management_units');
    }

    private function createTechnicalResponsiblesTable(): void
    {
        if ($this->db->tableExists('technical_responsibles')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'SERIAL', 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => false],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false, 'default' => ''],
            'email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => false, 'default' => ''],
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
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name', 'uq_technical_responsibles_name');
        $this->forge->createTable('technical_responsibles');
    }

    private function createRelationshipTable(): void
    {
        if ($this->db->tableExists('management_unit_technical_responsibles')) {
            return;
        }

        $this->forge->addField([
            'management_unit_id' => ['type' => 'INTEGER', 'null' => false],
            'technical_responsible_id' => ['type' => 'INTEGER', 'null' => false],
        ]);
        $this->forge->addKey(['management_unit_id', 'technical_responsible_id'], true);
        $this->forge->addForeignKey(
            'management_unit_id',
            'management_units',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_management_technical_management'
        );
        $this->forge->addForeignKey(
            'technical_responsible_id',
            'technical_responsibles',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_management_technical_responsible'
        );
        $this->forge->createTable('management_unit_technical_responsibles');
    }

    private function importExistingAssignments(): void
    {
        $knownManagementUnits = [
            'Administração de Banco de Dados',
            'Ativos',
            'Disponibilidade',
            'Continuidade',
            'Projetos Judiciarios - Aplicações',
        ];

        $hostRows = [];
        if ($this->db->tableExists('hosts_info')) {
            $hostRows = $this->db->table('hosts_info')
                ->select('vm, gerencia, owner')
                ->get()
                ->getResultArray();
        }

        foreach ($hostRows as $row) {
            $name = trim((string) ($row['gerencia'] ?? ''));
            if ($name !== '' && $name !== 'Sem registro') {
                $knownManagementUnits[] = $name;
            }
        }

        foreach (array_values(array_unique($knownManagementUnits)) as $name) {
            if (! $this->db->table('management_units')->where('name', $name)->countAllResults()) {
                $this->db->table('management_units')->insert([
                    'name' => $name,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $managementRows = $this->db->table('management_units')->select('id, name')->get()->getResultArray();
        $managementIds = [];
        foreach ($managementRows as $row) {
            $managementIds[(string) $row['name']] = (int) $row['id'];
        }

        foreach ($hostRows as $row) {
            $owner = trim((string) ($row['owner'] ?? ''));
            if ($owner === '' || in_array($owner, ['Sem registro', 'Nao informado'], true)) {
                continue;
            }
            if (! $this->db->table('technical_responsibles')->where('name', $owner)->countAllResults()) {
                $this->db->table('technical_responsibles')->insert([
                    'name' => $owner,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $responsibleRows = $this->db->table('technical_responsibles')->select('id, name')->get()->getResultArray();
        $responsibleIds = [];
        foreach ($responsibleRows as $row) {
            $responsibleIds[(string) $row['name']] = (int) $row['id'];
        }

        $relationships = [];
        foreach ($hostRows as $row) {
            $managementId = $managementIds[trim((string) ($row['gerencia'] ?? ''))] ?? null;
            $responsibleId = $responsibleIds[trim((string) ($row['owner'] ?? ''))] ?? null;
            if ($managementId !== null && $responsibleId !== null) {
                $key = $managementId . ':' . $responsibleId;
                if (! isset($relationships[$key])) {
                    $this->db->table('management_unit_technical_responsibles')->insert([
                        'management_unit_id' => $managementId,
                        'technical_responsible_id' => $responsibleId,
                    ]);
                    $relationships[$key] = true;
                }
            }

            $this->db->table('hosts_info')
                ->where('vm', (string) ($row['vm'] ?? ''))
                ->update([
                    'management_unit_id' => $managementId,
                    'technical_responsible_id' => $responsibleId,
                ]);
        }
    }
}
