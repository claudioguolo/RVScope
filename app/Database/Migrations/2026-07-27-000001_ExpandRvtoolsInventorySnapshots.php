<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExpandRvtoolsInventorySnapshots extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('rvtools_vm_inventory')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE rvtools_vm_inventory
                ADD COLUMN IF NOT EXISTS power_state VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS dns_name TEXT NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS primary_ip TEXT NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS os_name_raw TEXT NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS creation_date_raw TEXT NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS annotation TEXT NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS source_filename TEXT NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS source_sha256 CHAR(64) NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS raw_data JSONB NOT NULL DEFAULT '{}'::jsonb,
                ADD COLUMN IF NOT EXISTS included_in_reports BOOLEAN NOT NULL DEFAULT TRUE,
                ADD COLUMN IF NOT EXISTS imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
        );

        $this->db->query(
            'CREATE INDEX IF NOT EXISTS idx_rvtools_inventory_date_included
             ON rvtools_vm_inventory (reference_date, included_in_reports)'
        );
        $this->db->query(
            'CREATE INDEX IF NOT EXISTS idx_rvtools_inventory_date_os
             ON rvtools_vm_inventory (reference_date, os_name)'
        );
    }

    public function down()
    {
        if (! $this->db->tableExists('rvtools_vm_inventory')) {
            return;
        }

        $this->db->query('DROP INDEX IF EXISTS idx_rvtools_inventory_date_included');
        $this->db->query('DROP INDEX IF EXISTS idx_rvtools_inventory_date_os');
        $this->db->query(
            'ALTER TABLE rvtools_vm_inventory
                DROP COLUMN IF EXISTS imported_at,
                DROP COLUMN IF EXISTS included_in_reports,
                DROP COLUMN IF EXISTS raw_data,
                DROP COLUMN IF EXISTS source_sha256,
                DROP COLUMN IF EXISTS source_filename,
                DROP COLUMN IF EXISTS annotation,
                DROP COLUMN IF EXISTS creation_date_raw,
                DROP COLUMN IF EXISTS os_name_raw,
                DROP COLUMN IF EXISTS primary_ip,
                DROP COLUMN IF EXISTS dns_name,
                DROP COLUMN IF EXISTS power_state'
        );
    }
}
