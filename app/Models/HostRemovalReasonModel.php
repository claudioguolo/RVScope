<?php

namespace App\Models;

use CodeIgniter\Model;

class HostRemovalReasonModel extends Model
{
    protected $table = 'host_removal_reasons';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'reference_date',
        'vm',
        'reason',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public function reasonsForDate(string $date): array
    {
        $map = [];
        foreach ($this->where('reference_date', $date)->findAll() as $row) {
            $vm = trim((string) ($row['vm'] ?? ''));
            if ($vm !== '') {
                $map[$vm] = (string) ($row['reason'] ?? '');
            }
        }

        return $map;
    }

    public function setReason(string $date, string $vm, string $reason, string $updatedBy): void
    {
        $now = date('Y-m-d H:i:s');
        $builder = $this->db->table($this->table);
        $exists = $builder
            ->where('reference_date', $date)
            ->where('vm', $vm)
            ->countAllResults() > 0;

        $data = [
            'reason' => $reason,
            'updated_by' => $updatedBy,
            'updated_at' => $now,
        ];

        if ($exists) {
            $builder
                ->where('reference_date', $date)
                ->where('vm', $vm)
                ->update($data);
            return;
        }

        $builder->insert($data + [
            'reference_date' => $date,
            'vm' => $vm,
            'created_at' => $now,
        ]);
    }
}
