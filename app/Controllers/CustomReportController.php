<?php

namespace App\Controllers;

use App\Libraries\CustomReportCriteria;
use App\Models\ManagementUnitModel;
use App\Models\ManagementUnitTechnicalResponsibleModel;
use App\Models\TechnicalResponsibleModel;
use CodeIgniter\Controller;

class CustomReportController extends Controller
{
    public function index()
    {
        $db = db_connect();
        $dates = array_column(
            $db->table('rvtools_vm_inventory')
                ->distinct()
                ->select('reference_date')
                ->orderBy('reference_date', 'DESC')
                ->get()
                ->getResultArray(),
            'reference_date'
        );
        $operatingSystems = array_column(
            $db->table('rvtools_vm_inventory')
                ->distinct()
                ->select('os_name')
                ->where('included_in_reports', true)
                ->where('os_name !=', '')
                ->orderBy('os_name', 'ASC')
                ->get()
                ->getResultArray(),
            'os_name'
        );
        $managementUnits = $db->table('management_units')
            ->select('id, name')
            ->where('is_deleted', false)
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $submitted = $this->request->getGet('generate') === '1';
        $criteria = CustomReportCriteria::fromArray(
            $this->request->getGet(),
            (string) ($dates[0] ?? '')
        );
        $rows = [];
        $error = null;

        if ($submitted) {
            if (! $criteria->hasValidDate() || ! in_array($criteria->date, $dates, true)) {
                $error = 'Selecione uma data de inventário válida.';
            } else {
                $rows = $this->loadSummary($criteria);
            }
        }

        if ($submitted && $error === null && $this->request->getGet('export') === 'csv') {
            return $this->exportCsv($rows, $criteria);
        }

        return view('reports/custom', [
            'dates' => $dates,
            'operatingSystems' => $operatingSystems,
            'managementUnits' => $managementUnits,
            'criteria' => $criteria,
            'submitted' => $submitted,
            'rows' => $rows,
            'error' => $error,
        ]);
    }

    public function detail()
    {
        $criteria = CustomReportCriteria::fromArray($this->request->getGet());
        $groupName = trim((string) ($this->request->getGet('group_name') ?? ''));
        if (! $criteria->hasValidDate() || $groupName === '') {
            return redirect()->to(site_url('reports/personalizado'));
        }

        $rows = $this->loadDetailRows($criteria, $groupName);
        if ($this->request->getGet('export') === 'csv') {
            return $this->exportDetailCsv($rows, $criteria, $groupName);
        }

        return view('reports/custom_detail', [
            'criteria' => $criteria,
            'groupName' => $groupName,
            'rows' => $rows,
            'backUrl' => site_url('reports/personalizado?' . http_build_query(
                $this->criteriaParameters($criteria) + ['generate' => '1']
            )),
            'alert' => session()->getFlashdata('hostInfoAlert'),
        ] + $this->hostCatalogViewData());
    }

    private function loadSummary(CustomReportCriteria $criteria): array
    {
        $builder = db_connect()->table('rvtools_vm_inventory inv');
        $builder->join('hosts_info info', 'info.vm = inv.vm', 'left');
        $builder->join('management_units mu', 'mu.id = info.management_unit_id', 'left');
        $this->applyFilters($builder, $criteria);

        $groupExpression = $this->groupExpression($criteria);

        return $builder
            ->select($groupExpression . ' AS group_name, COUNT(*) AS vm_count', false)
            ->groupBy($groupExpression, false)
            ->orderBy('group_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function loadDetailRows(CustomReportCriteria $criteria, string $groupName): array
    {
        $builder = db_connect()->table('rvtools_vm_inventory inv');
        $builder->select(
            "inv.vm, inv.dns_name, inv.primary_ip, inv.os_name, inv.annotation,
             COALESCE(NULLIF(TRIM(info.operating_system_override), ''), NULLIF(TRIM(inv.os_name_raw), ''), inv.os_name) AS os_name_display,
             COALESCE(NULLIF(TRIM(mu.name), ''), 'Sem registro') AS gerencia,
             COALESCE(info.desc, 'Sem registro') AS description,
             COALESCE(info.operating_system_override, '') AS operating_system_override,
             info.management_unit_id,
             COALESCE(mu.is_active, FALSE) AS management_unit_is_active,
             info.technical_responsible_id,
             COALESCE(tr.name, 'Sem registro') AS owner,
             COALESCE(tr.is_active, FALSE) AS technical_responsible_is_active,
             COALESCE(info.leg, 0) AS leg,
             COALESCE(info.app, 0) AS app,
             COALESCE(info.mig, 0) AS mig,
             COALESCE(info.migration_target, 'none') AS migration_target,
             COALESCE(info.worker, 'none') AS worker,
             COALESCE(info.conv, 'Nao informado') AS conv,
             COALESCE(info.creation_date, '') AS host_creation_date,
             COALESCE(info.os_last_update_date::text, '') AS os_last_update_date,
             COALESCE(info.has_contract, FALSE) AS has_contract,
             COALESCE(info.contract, '') AS contract,
             COALESCE(info.contract_valid_until::text, '') AS contract_valid_until,
             COALESCE(info.asset_risk_score, '') AS asset_risk_score"
        );
        $builder->join('hosts_info info', 'info.vm = inv.vm', 'left');
        $builder->join('management_units mu', 'mu.id = info.management_unit_id', 'left');
        $builder->join('technical_responsibles tr', 'tr.id = info.technical_responsible_id', 'left');
        $this->applyFilters($builder, $criteria);
        $builder->where(
            $this->groupExpression($criteria) . ' = ' . db_connect()->escape($groupName),
            null,
            false,
        );

        $rows = $builder->orderBy('inv.vm', 'ASC')->get()->getResultArray();
        $displayName = new \App\Libraries\OperatingSystemDisplayName();
        foreach ($rows as &$row) {
            $row['os_name_display'] = $displayName->clean(
                (string) ($row['os_name_display'] ?? $row['os_name'] ?? '')
            );
        }
        unset($row);

        return $rows;
    }

    private function applyFilters($builder, CustomReportCriteria $criteria): void
    {
        $builder->where('inv.reference_date', $criteria->date);
        $builder->where('inv.included_in_reports', true);
        if ($criteria->operatingSystems !== []) {
            $builder->whereIn('inv.os_name', $criteria->operatingSystems);
        }
        if ($criteria->managementUnitIds !== []) {
            $builder->whereIn('info.management_unit_id', $criteria->managementUnitIds);
        }
        $categoryFilters = [];
        if ($criteria->legacy) {
            $categoryFilters[] = ['info.leg', 1];
        }
        if ($criteria->appliance) {
            $categoryFilters[] = ['info.app', 1];
        }
        if ($criteria->migrable) {
            $categoryFilters[] = ['info.mig', 1];
        }
        if ($categoryFilters !== []) {
            $builder->groupStart()
                ->where($categoryFilters[0][0], $categoryFilters[0][1]);
            foreach (array_slice($categoryFilters, 1) as [$field, $value]) {
                $builder->orWhere($field, $value);
            }
            $builder->groupEnd();
        }
    }

    private function groupExpression(CustomReportCriteria $criteria): string
    {
        return $criteria->groupBy === CustomReportCriteria::GROUP_OPERATING_SYSTEM
            ? "COALESCE(NULLIF(TRIM(inv.os_name), ''), 'Sem registro')"
            : "COALESCE(NULLIF(TRIM(mu.name), ''), 'Sem registro')";
    }

    private function exportCsv(array $rows, CustomReportCriteria $criteria)
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return $this->response->setStatusCode(500)->setBody('Não foi possível gerar o CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        $groupLabel = $criteria->groupBy === CustomReportCriteria::GROUP_OPERATING_SYSTEM
            ? 'Sistema operacional'
            : 'Gerência';
        fputcsv($stream, [$groupLabel, 'Quantidade de VMs'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['group_name'] ?? 'Sem registro',
                (int) ($row['vm_count'] ?? 0),
            ], ';', '"', '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="RVScope_relatorio_personalizado_' . $criteria->date . '.csv"')
            ->setBody($content === false ? '' : $content);
    }

    private function exportDetailCsv(array $rows, CustomReportCriteria $criteria, string $groupName)
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return $this->response->setStatusCode(500)->setBody('Não foi possível gerar o CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Ordem', 'VM', 'DNS', 'IP', 'Sistema operacional', 'Gerência', 'Última atualização',
            'Legado', 'Appliance', 'Migrável', 'Contrato', 'Asset risk score (ASTI)',
        ], ';', '"', '');
        foreach ($rows as $index => $row) {
            fputcsv($stream, [
                $index + 1,
                $row['vm'] ?? '',
                $row['dns_name'] ?? '',
                $row['primary_ip'] ?? '',
                $row['os_name_display'] ?? $row['os_name'] ?? '',
                $row['gerencia'] ?? 'Sem registro',
                $this->formatDate((string) ($row['os_last_update_date'] ?? '')),
                (int) ($row['leg'] ?? 0) === 1 ? 'Sim' : 'Não',
                (int) ($row['app'] ?? 0) === 1 ? 'Sim' : 'Não',
                (int) ($row['mig'] ?? 0) === 1 ? 'Sim' : 'Não',
                $row['contract'] ?? '',
                $row['asset_risk_score'] ?? '',
            ], ';', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        $safeGroup = preg_replace('/[^A-Za-z0-9_-]+/', '_', $groupName) ?: 'grupo';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="RVScope_relatorio_personalizado_' . $criteria->date . '_' . $safeGroup . '.csv"')
            ->setBody($content === false ? '' : $content);
    }

    private function criteriaParameters(CustomReportCriteria $criteria): array
    {
        $parameters = [
            'date' => $criteria->date,
            'group_by' => $criteria->groupBy,
            'os' => $criteria->operatingSystems,
            'management_unit_id' => $criteria->managementUnitIds,
        ];
        foreach (['legacy', 'appliance', 'migrable'] as $flag) {
            if ($criteria->{$flag}) {
                $parameters[$flag] = '1';
            }
        }

        return $parameters;
    }

    private function formatDate(string $value): string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date === false ? $value : $date->format('d/m/Y');
    }

    private function hostCatalogViewData(): array
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
}
