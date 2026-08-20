<?php

namespace App\Controllers;

use App\Libraries\InventoryByManagementReport;
use CodeIgniter\Controller;
use DateTime;
use Throwable;

class HostInventoryController extends Controller
{
    public function index()
    {
        $rows = [];
        $referenceDate = '';
        $error = null;

        try {
            $db = db_connect();
            $latest = $db->table('rvtools_vm_inventory')
                ->selectMax('reference_date')
                ->get()
                ->getRowArray();
            $referenceDate = trim((string) ($latest['reference_date'] ?? ''));

            if ($referenceDate !== '') {
                $rows = $db->query(
                    "SELECT inv.vm,
                            inv.dns_name,
                            inv.primary_ip,
                            COALESCE(NULLIF(TRIM(info.gerencia), ''), 'Sem registro') AS gerencia,
                            COALESCE(info.os_last_update_date::text, '') AS os_last_update_date,
                            COALESCE(info.contract, '') AS contract,
                            COALESCE(info.asset_risk_score, '') AS asset_risk_score
                     FROM rvtools_vm_inventory inv
                     LEFT JOIN hosts_info info ON info.vm = inv.vm
                     WHERE inv.reference_date = ?
                       AND inv.included_in_reports = TRUE
                     ORDER BY gerencia ASC, inv.vm ASC",
                    [$referenceDate]
                )->getResultArray();
            }
        } catch (Throwable $exception) {
            log_message('error', 'Falha ao carregar inventário por gerência: {message}', [
                'message' => $exception->getMessage(),
            ]);
            $error = 'Banco de dados indisponível. O relatório não pôde ser carregado agora.';
        }

        $groups = (new InventoryByManagementReport())->group($rows);

        if ($this->request->getGet('export') === 'csv' && $error === null) {
            return $this->exportCsv($rows, $referenceDate);
        }

        return view('reports/host_inventory_by_management', [
            'groups' => $groups,
            'referenceDate' => $referenceDate,
            'error' => $error,
        ]);
    }

    private function exportCsv(array $rows, string $referenceDate)
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return $this->response->setStatusCode(500)->setBody('Não foi possível gerar o CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Gerência',
            'Nome VMware',
            'DNS',
            'IP',
            'Data da última atualização',
            'Informações de contrato',
            'Asset risk score (ASTI)',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['gerencia'] ?? 'Sem registro',
                $row['vm'] ?? '',
                $row['dns_name'] ?? '',
                $row['primary_ip'] ?? '',
                $this->formatDate((string) ($row['os_last_update_date'] ?? '')),
                $row['contract'] ?? '',
                $row['asset_risk_score'] ?? '',
            ], ';');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        $suffix = $referenceDate !== '' ? '_' . $referenceDate : '';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="RVScope_inventario_por_gerencia' . $suffix . '.csv"')
            ->setBody($content === false ? '' : $content);
    }

    private function formatDate(string $value): string
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date === false ? $value : $date->format('d/m/Y');
    }
}
