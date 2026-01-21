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
        'owner',
        'conv',
        'leg',
        'mig',
        'app',
        'creation_date',
        'updated_at',
    ];
}
