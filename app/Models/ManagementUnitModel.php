<?php

namespace App\Models;

use CodeIgniter\Model;

class ManagementUnitModel extends Model
{
    protected $table = 'management_units';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'name',
        'department',
        'manager_name',
        'management_email',
        'created_at',
        'updated_at',
    ];
}
