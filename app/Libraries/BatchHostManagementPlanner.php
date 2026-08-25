<?php

namespace App\Libraries;

final class BatchHostManagementPlanner
{
    public function plan(array $hosts, int $destinationManagementUnitId, array $allowedResponsibleIds): array
    {
        $allowed = array_fill_keys(array_map('intval', $allowedResponsibleIds), true);
        $updates = [];
        $clearedResponsibleCount = 0;

        foreach ($hosts as $host) {
            $vm = trim((string) ($host['vm'] ?? ''));
            if ($vm === '') {
                continue;
            }

            $responsibleId = (int) ($host['technical_responsible_id'] ?? 0);
            $keepResponsible = $responsibleId > 0 && isset($allowed[$responsibleId]);
            if ($responsibleId > 0 && ! $keepResponsible) {
                $clearedResponsibleCount++;
            }

            $updates[] = [
                'vm' => $vm,
                'management_unit_id' => $destinationManagementUnitId,
                'technical_responsible_id' => $keepResponsible ? $responsibleId : null,
            ];
        }

        return [
            'updates' => $updates,
            'cleared_responsible_count' => $clearedResponsibleCount,
        ];
    }
}
