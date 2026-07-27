<?php

namespace App\Models;

use CodeIgniter\Model;

class RvtoolsVmInventoryModel extends Model
{
    protected $table = 'rvtools_vm_inventory';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'reference_date',
        'vm',
        'os_name',
        'power_state',
        'dns_name',
        'primary_ip',
        'os_name_raw',
        'creation_date_raw',
        'annotation',
        'source_filename',
        'source_sha256',
        'raw_data',
        'included_in_reports',
        'imported_at',
    ];
}
