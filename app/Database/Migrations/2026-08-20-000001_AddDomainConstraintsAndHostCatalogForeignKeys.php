<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDomainConstraintsAndHostCatalogForeignKeys extends Migration
{
    public function up()
    {
        $this->constrainUsers();
        $this->constrainHosts();
    }

    public function down()
    {
        if ($this->db->tableExists('hosts_info')) {
            foreach ([
                'fk_hosts_info_management_unit',
                'fk_hosts_info_technical_responsible',
                'fk_hosts_info_management_responsible_pair',
                'chk_hosts_info_flags',
                'chk_hosts_info_worker',
                'chk_hosts_info_migration_target',
            ] as $constraint) {
                $this->db->query("ALTER TABLE hosts_info DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        }

        if ($this->db->tableExists('admin_users')) {
            $this->db->query('ALTER TABLE admin_users DROP CONSTRAINT IF EXISTS chk_admin_users_role');
            $this->db->query('ALTER TABLE admin_users DROP CONSTRAINT IF EXISTS chk_admin_users_auth_source');
        }
    }

    private function constrainUsers(): void
    {
        if (! $this->db->tableExists('admin_users')) {
            return;
        }

        $this->db->query("UPDATE admin_users SET role = 'user' WHERE role IS NULL OR role NOT IN ('user', 'editor', 'admin')");
        $this->db->query("UPDATE admin_users SET auth_source = 'local' WHERE auth_source IS NULL OR auth_source NOT IN ('local', 'ad')");
        $this->addConstraint(
            'admin_users',
            'chk_admin_users_role',
            "CHECK (role IN ('user', 'editor', 'admin'))",
        );
        $this->addConstraint(
            'admin_users',
            'chk_admin_users_auth_source',
            "CHECK (auth_source IN ('local', 'ad'))",
        );
    }

    private function constrainHosts(): void
    {
        if (! $this->db->tableExists('hosts_info')) {
            return;
        }

        $this->db->query("UPDATE hosts_info SET worker = 'none' WHERE worker IS NULL OR worker NOT IN ('none', 'openshift', 'rancher')");
        $this->db->query('UPDATE hosts_info SET leg = CASE WHEN leg = 1 THEN 1 ELSE 0 END, mig = CASE WHEN mig = 1 THEN 1 ELSE 0 END, app = CASE WHEN app = 1 THEN 1 ELSE 0 END');
        $this->db->query("UPDATE hosts_info SET migration_target = CASE WHEN mig = 1 AND migration_target IN ('other_host', 'openshift') THEN migration_target WHEN mig = 1 THEN 'other_host' ELSE 'none' END");

        $this->addConstraint('hosts_info', 'chk_hosts_info_flags', 'CHECK (leg IN (0, 1) AND mig IN (0, 1) AND app IN (0, 1))');
        $this->addConstraint('hosts_info', 'chk_hosts_info_worker', "CHECK (worker IN ('none', 'openshift', 'rancher'))");
        $this->addConstraint('hosts_info', 'chk_hosts_info_migration_target', "CHECK ((mig = 0 AND migration_target = 'none') OR (mig = 1 AND migration_target IN ('other_host', 'openshift')))");

        if ($this->db->tableExists('management_units')
            && $this->db->fieldExists('management_unit_id', 'hosts_info')) {
            $this->db->query('UPDATE hosts_info h SET management_unit_id = NULL WHERE management_unit_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM management_units m WHERE m.id = h.management_unit_id)');
            $this->addConstraint(
                'hosts_info',
                'fk_hosts_info_management_unit',
                'FOREIGN KEY (management_unit_id) REFERENCES management_units(id) ON UPDATE CASCADE ON DELETE SET NULL',
            );
        }

        if ($this->db->tableExists('technical_responsibles')
            && $this->db->fieldExists('technical_responsible_id', 'hosts_info')) {
            $this->db->query('UPDATE hosts_info h SET technical_responsible_id = NULL WHERE technical_responsible_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM technical_responsibles r WHERE r.id = h.technical_responsible_id)');
            $this->addConstraint(
                'hosts_info',
                'fk_hosts_info_technical_responsible',
                'FOREIGN KEY (technical_responsible_id) REFERENCES technical_responsibles(id) ON UPDATE CASCADE ON DELETE SET NULL',
            );
        }

        if ($this->db->tableExists('management_unit_technical_responsibles')
            && $this->db->fieldExists('management_unit_id', 'hosts_info')
            && $this->db->fieldExists('technical_responsible_id', 'hosts_info')) {
            $this->db->query(
                'UPDATE hosts_info h
                 SET management_unit_id = NULL, technical_responsible_id = NULL
                 WHERE h.management_unit_id IS NOT NULL
                   AND h.technical_responsible_id IS NOT NULL
                   AND NOT EXISTS (
                       SELECT 1
                       FROM management_unit_technical_responsibles rel
                       WHERE rel.management_unit_id = h.management_unit_id
                         AND rel.technical_responsible_id = h.technical_responsible_id
                   )'
            );
            $this->addConstraint(
                'hosts_info',
                'fk_hosts_info_management_responsible_pair',
                'FOREIGN KEY (management_unit_id, technical_responsible_id) REFERENCES management_unit_technical_responsibles(management_unit_id, technical_responsible_id) ON UPDATE CASCADE ON DELETE SET NULL',
            );
        }
    }

    private function addConstraint(string $table, string $name, string $definition): void
    {
        $this->db->query(
            "DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$name}') THEN
                    ALTER TABLE {$table} ADD CONSTRAINT {$name} {$definition} NOT VALID;
                END IF;
            END \$\$"
        );
        $this->db->query("ALTER TABLE {$table} VALIDATE CONSTRAINT {$name}");
    }
}
