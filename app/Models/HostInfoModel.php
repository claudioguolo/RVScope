<?php

namespace App\Models;

use CodeIgniter\Model;

class HostInfoModel extends Model
{
    protected $table = 'hosts_info';
    protected $primaryKey = 'vm';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'vm',
        'desc',
        'gerencia',
        'management_unit_id',
        'owner',
        'technical_responsible_id',
        'conv',
        'leg',
        'mig',
        'migration_target',
        'app',
        'worker',
        'creation_date',
        'os_last_update_date',
        'updated_at',
    ];
}
