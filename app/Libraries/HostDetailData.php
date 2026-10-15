<?php

namespace App\Libraries;

use App\Models\ManagementUnitModel;
use App\Models\ManagementUnitTechnicalResponsibleModel;
use App\Models\TechnicalResponsibleModel;

final class HostDetailData
{
    public function catalogs(): array
    {
        $managementUnits = (new ManagementUnitModel())
            ->where('is_deleted', false)
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
        $activeResponsibles = (new TechnicalResponsibleModel())
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
        $responsiblesById = [];
        foreach ($activeResponsibles as $responsible) {
            $id = (int) ($responsible['id'] ?? 0);
            if ($id > 0) {
                $responsiblesById[$id] = ['id' => $id, 'name' => (string) ($responsible['name'] ?? '')];
            }
        }

        $byManagementUnit = [];
        foreach ((new ManagementUnitTechnicalResponsibleModel())->findAll() as $relationship) {
            $managementId = (int) ($relationship['management_unit_id'] ?? 0);
            $responsibleId = (int) ($relationship['technical_responsible_id'] ?? 0);
            if ($managementId > 0 && isset($responsiblesById[$responsibleId])) {
                $byManagementUnit[$managementId][] = $responsiblesById[$responsibleId];
            }
        }
        foreach ($byManagementUnit as &$responsibles) {
            usort($responsibles, static fn (array $left, array $right): int => strcasecmp(
                (string) ($left['name'] ?? ''),
                (string) ($right['name'] ?? '')
            ));
        }
        unset($responsibles);

        $operatingSystems = db_connect()->table('operating_system_policies')
            ->select('os_name')
            ->orderBy('LOWER(os_name)', 'ASC', false)
            ->get()->getResultArray();

        return [
            'managementUnits' => $managementUnits,
            'technicalResponsiblesByManagementUnit' => $byManagementUnit,
            'operatingSystems' => array_column($operatingSystems, 'os_name'),
        ];
    }

    public function enrich(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $information = db_connect()->table('hosts_info info')
            ->select('info.*, mu.is_active AS management_unit_is_active, tr.is_active AS technical_responsible_is_active')
            ->join('management_units mu', 'mu.id = info.management_unit_id', 'left')
            ->join('technical_responsibles tr', 'tr.id = info.technical_responsible_id', 'left')
            ->whereIn('info.vm', array_column($rows, 'vm'))->get()->getResultArray();
        $byVm = array_column($information, null, 'vm');
        foreach ($rows as &$row) {
            $row['info'] = $byVm[$row['vm']] ?? [];
        }
        unset($row);
        return $rows;
    }
}
