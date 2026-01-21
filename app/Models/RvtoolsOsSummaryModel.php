<?php

namespace App\Models;

use CodeIgniter\Model;

class RvtoolsOsSummaryModel extends Model
{
    protected $table = 'rvtools_os_summary';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'reference_date',
        'os_name',
        'vm_count',
    ];
}
