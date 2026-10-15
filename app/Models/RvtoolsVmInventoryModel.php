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
    public function searchHosts(string $date, string $term, int $page = 1): array
    {
        $builder = $this->builder()->select('vm, dns_name, primary_ip, os_name, os_name_raw, creation_date_raw, annotation, power_state')
            ->where('reference_date', $date)
            ->groupStart()
            ->like('vm', $term, 'both', true, true)
            ->orLike('dns_name', $term, 'both', true, true)
            ->orLike('primary_ip', $term, 'both', true, true)
            ->groupEnd();
        $total = $builder->countAllResults(false);
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));
        $rows = $builder->orderBy('vm', 'ASC')->orderBy('id', 'ASC')
            ->limit(50, ($page - 1) * 50)->get()->getResultArray();
        return compact('rows', 'total', 'page', 'pages');
    }
}
