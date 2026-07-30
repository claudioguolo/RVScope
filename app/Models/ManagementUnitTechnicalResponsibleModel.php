<?php

namespace App\Models;

use CodeIgniter\Model;

class ManagementUnitTechnicalResponsibleModel extends Model
{
    protected $table = 'management_unit_technical_responsibles';
    protected $primaryKey = 'management_unit_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'management_unit_id',
        'technical_responsible_id',
    ];
}
