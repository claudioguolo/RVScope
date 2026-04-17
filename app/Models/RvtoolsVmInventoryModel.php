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
    ];
}
