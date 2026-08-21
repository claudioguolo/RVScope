<?php

namespace App\Models;

use CodeIgniter\Model;

class TechnicalResponsibleModel extends Model
{
    protected $table = 'technical_responsibles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'name',
        'phone',
        'email',
        'is_active',
        'created_at',
        'updated_at',
    ];
}
