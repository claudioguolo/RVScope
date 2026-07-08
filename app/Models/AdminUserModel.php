<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminUserModel extends Model
{
    protected $table = 'admin_users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'username',
        'display_name',
        'password_hash',
        'role',
        'is_active',
        'last_login_at',
        'created_at',
        'updated_at',
    ];
}
