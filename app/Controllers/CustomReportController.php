<?php

namespace App\Controllers;

use App\Libraries\CustomReportCriteria;
use App\Libraries\OperatingSystemDisplayName;
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
                $rows = $this->loadRows($criteria);
            }
        }

        if ($submitted && $error === null && $this->request->getGet('export') === 'csv') {
            return $this->exportCsv($rows, $criteria->date);
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

    private function loadRows(CustomReportCriteria $criteria): array
    {
        $builder = db_connect()->table('rvtools_vm_inventory inv');
        $builder->select(
            "inv.vm, inv.dns_name, inv.primary_ip, inv.os_name, inv.power_state,
             COALESCE(NULLIF(TRIM(inv.os_name_raw), ''), inv.os_name) AS os_name_display,
             COALESCE(NULLIF(TRIM(mu.name), ''), 'Sem registro') AS gerencia,
             COALESCE(info.leg, 0) AS leg,
             COALESCE(info.app, 0) AS app,
             COALESCE(info.contract, '') AS contract,
             COALESCE(info.asset_risk_score, '') AS asset_risk_score"
        );
        $builder->join('hosts_info info', 'info.vm = inv.vm', 'left');
        $builder->join('management_units mu', 'mu.id = info.management_unit_id', 'left');
        $builder->where('inv.reference_date', $criteria->date);
        $builder->where('inv.included_in_reports', true);

        if ($criteria->operatingSystems !== []) {
            $builder->whereIn('inv.os_name', $criteria->operatingSystems);
        }
        if ($criteria->managementUnitIds !== []) {
            $builder->whereIn('info.management_unit_id', $criteria->managementUnitIds);
        }
        if ($criteria->legacy && $criteria->appliance) {
            $builder->groupStart()
                ->where('info.leg', 1)
                ->orWhere('info.app', 1)
                ->groupEnd();
        } elseif ($criteria->legacy) {
            $builder->where('info.leg', 1);
        } elseif ($criteria->appliance) {
            $builder->where('info.app', 1);
        }

        $rows = $builder->orderBy('mu.name', 'ASC')
            ->orderBy('inv.vm', 'ASC')
            ->get()
            ->getResultArray();

        $displayName = new OperatingSystemDisplayName();
        foreach ($rows as &$row) {
            $row['os_name_display'] = $displayName->clean(
                (string) ($row['os_name_display'] ?? $row['os_name'] ?? '')
            );
        }
        unset($row);

        return $rows;
    }

    private function exportCsv(array $rows, string $date)
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return $this->response->setStatusCode(500)->setBody('Não foi possível gerar o CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'VM', 'DNS', 'IP', 'Sistema operacional', 'Gerência', 'Legado', 'Appliance',
            'Contrato', 'Asset risk score (ASTI)',
        ], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['vm'] ?? '',
                $row['dns_name'] ?? '',
                $row['primary_ip'] ?? '',
                $row['os_name_display'] ?? $row['os_name'] ?? '',
                $row['gerencia'] ?? 'Sem registro',
                (int) ($row['leg'] ?? 0) === 1 ? 'Sim' : 'Não',
                (int) ($row['app'] ?? 0) === 1 ? 'Sim' : 'Não',
                $row['contract'] ?? '',
                $row['asset_risk_score'] ?? '',
            ], ';', '"', '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="RVScope_relatorio_personalizado_' . $date . '.csv"')
            ->setBody($content === false ? '' : $content);
    }
}
