<?php

namespace App\Models;

use CodeIgniter\Model;

class RvtoolsImportLogModel extends Model
{
    protected $table = 'rvtools_import_log';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'filename',
        'reference_date',
    ];
}
