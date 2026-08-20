<?php

namespace App\Controllers;

use App\Libraries\UserAuthorization;
use App\Libraries\ReportRowFilter;
use App\Models\HostInfoModel;
use App\Models\HostRemovalReasonModel;
use App\Models\ManagementUnitModel;
use App\Models\ManagementUnitTechnicalResponsibleModel;
use App\Models\RvtoolsOsSummaryModel;
use App\Models\RvtoolsVmInventoryModel;
use App\Models\TechnicalResponsibleModel;
use CodeIgniter\Controller;
use Config\Rvtools as RvtoolsConfig;
use DateTime;

class ReportController extends Controller
{
    private ?ReportRowFilter $reportRowFilter = null;

    public function index()
    {
        return $this->renderVmTodos([
            'subtitle' => 'Inventario historico de VMs por sistema operacional.',
            'activeMenu' => 'inicio',
            'activeSubmenu' => '',
            'breadcrumbs' => [
                ['label' => 'Início', 'active' => true],
            ],
        ]);
    }

    public function vmTodos()
    {
        return $this->renderVmTodos([
            'subtitle' => 'Relatorio de VMs agrupado por sistema operacional.',
            'activeMenu' => 'relatorios',
            'activeSubmenu' => 'vm-todos',
            'breadcrumbs' => [
                ['label' => 'Início', 'url' => site_url('/')],
                ['label' => 'Relatórios'],
                ['label' => 'VM'],
                ['label' => 'Todos', 'active' => true],
            ],
        ]);
    }

    public function vmPorGerencia()
    {
        $legacyOnly = $this->request->getGet('legacy') === '1';
        $db = db_connect();
        $sql = "SELECT inv.reference_date,
                       COALESCE(NULLIF(TRIM(info.gerencia), ''), 'Sem registro') AS gerencia,
                       COUNT(*) AS vm_count
                FROM rvtools_vm_inventory inv
                LEFT JOIN hosts_info info ON info.vm = inv.vm
                WHERE inv.included_in_reports = TRUE";
        if ($legacyOnly) {
            $sql .= " AND info.leg = 1";
        }
        $sql .= " GROUP BY inv.reference_date, COALESCE(NULLIF(TRIM(info.gerencia), ''), 'Sem registro')
                  ORDER BY inv.reference_date DESC, gerencia ASC";
        $rows = $db->query($sql)->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $date = $row['reference_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'reference_date' => $date,
                    'items' => [],
                    'total' => 0,
                ];
            }

            $row['vm_count'] = (int) ($row['vm_count'] ?? 0);
            $grouped[$date]['items'][] = $row;
            $grouped[$date]['total'] += $row['vm_count'];
        }

        if ($this->request->getGet('export') === 'csv') {
            $exportDate = $this->selectedExportDate();
            $exportRows = $this->rowFilter()->summariesForDate($rows, $exportDate);

            return $this->exportSummaryCsv(
                'RVScope_vm_por_gerencia' . ($legacyOnly ? '_legados' : '') . ($exportDate !== '' ? '_' . $exportDate : '') . '.csv',
                ['Data', 'Gerencia', 'Quantidade de VMs', 'Legados'],
                array_map(static function (array $row) use ($legacyOnly): array {
                    return [
                        $row['reference_date'] ?? '',
                        $row['gerencia'] ?? 'Sem registro',
                        (int) ($row['vm_count'] ?? 0),
                        $legacyOnly ? 'Sim' : 'Nao',
                    ];
                }, $exportRows)
            );
        }

        return view('reports/by_gerencia', [
            'days' => array_values($grouped),
            'legacyOnly' => $legacyOnly,
        ]);
    }

    public function vmPorGerenciaDetail()
    {
        $date = trim((string) ($this->request->getGet('date') ?? ''));
        if ($date === '') {
            $date = trim((string) ($this->request->getPost('date') ?? ''));
        }

        $gerencia = trim((string) ($this->request->getGet('gerencia') ?? ''));
        if ($gerencia === '') {
            $gerencia = trim((string) ($this->request->getPost('gerencia_filter') ?? ''));
        }
        if ($gerencia === '') {
            $gerencia = trim((string) ($this->request->getPost('gerencia') ?? ''));
        }
        $legacyOnly = $this->request->getGet('legacy') === '1'
            || $this->request->getPost('legacy_filter') === '1';

        $alert = null;
        $error = null;

        $infoModel = new HostInfoModel();
        $infoMap = $this->loadInfoMap($infoModel);

        $method = strtoupper($this->request->getMethod());
        $saveRequested = $method === 'POST' && $this->request->getPost('save_info') !== null;
        $exportRequested = $method === 'POST' && $this->request->getPost('export') !== null;

        if ($saveRequested) {
            $saveResult = $this->handleSave($infoModel);
            if ($saveResult['success']) {
                $alert = ['type' => 'success', 'message' => 'Salvo com sucesso!'];
                $infoMap = $this->loadInfoMap($infoModel);
            } else {
                $alert = ['type' => 'danger', 'message' => 'Erro: ' . $saveResult['message']];
            }
        }
        if ($method === 'POST' && $this->request->getPost('save_removal_reason') !== null) {
            $alert = $this->handleRemovalReasonSave($date);
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Data invalida.';
        }

        if ($error === null && $gerencia === '') {
            $error = 'Gerencia invalida.';
        }

        $rows = [];
        $newVmMap = [];
        if ($error === null) {
            $rows = $this->loadInventoryRows($date, '', $infoMap, $error);
            $rows = $this->appendRemovedInventoryRows($rows, $date, '', $infoMap, $error);
            $rows = $this->rowFilter()->byManagementUnit($rows, $gerencia);
            if ($legacyOnly) {
                $rows = $this->rowFilter()->legacy($rows);
            }
            $newVmMap = $this->findNewVmsForDateFromDb($date);
        }

        if ($error === null && $exportRequested) {
            return $this->exportCsv($rows, $date);
        }

        return view('reports/detail_by_gerencia', [
            'date' => $date,
            'gerencia' => $this->rowFilter()->normalizeManagementUnit($gerencia),
            'rows' => $rows,
            'alert' => $alert,
            'error' => $error,
            'newVmMap' => $newVmMap,
            'legacyOnly' => $legacyOnly,
        ] + $this->hostCatalogViewData());
    }

    public function vmMigraveis()
    {
        $db = db_connect();
        $rows = $db->query(
            "SELECT inv.reference_date, COUNT(*) AS vm_count
             FROM rvtools_vm_inventory inv
             INNER JOIN hosts_info info ON info.vm = inv.vm AND info.mig = 1
             WHERE inv.included_in_reports = TRUE
             GROUP BY inv.reference_date
            ORDER BY inv.reference_date DESC"
        )->getResultArray();

        if ($this->request->getGet('export') === 'csv') {
            $exportDate = $this->selectedExportDate();
            $exportRows = $this->rowFilter()->summariesForDate($rows, $exportDate);

            return $this->exportSummaryCsv(
                'RVScope_vm_migraveis' . ($exportDate !== '' ? '_' . $exportDate : '') . '.csv',
                ['Data', 'Escopo', 'Quantidade de VMs'],
                array_map(static function (array $row): array {
                    return [
                        $row['reference_date'] ?? '',
                        'Migraveis',
                        (int) ($row['vm_count'] ?? 0),
                    ];
                }, $exportRows)
            );
        }

        return view('reports/vm_migraveis', [
            'days' => array_map(static function (array $row): array {
                return [
                    'reference_date' => $row['reference_date'],
                    'vm_count' => (int) ($row['vm_count'] ?? 0),
                ];
            }, $rows),
        ]);
    }

    public function vmMigraveisDetail()
    {
        $date = trim((string) ($this->request->getGet('date') ?? ''));
        if ($date === '') {
            $date = trim((string) ($this->request->getPost('date') ?? ''));
        }

        $alert = null;
        $error = null;

        $infoModel = new HostInfoModel();
        $infoMap = $this->loadInfoMap($infoModel);

        $method = strtoupper($this->request->getMethod());
        $saveRequested = $method === 'POST' && $this->request->getPost('save_info') !== null;
        $exportRequested = $method === 'POST' && $this->request->getPost('export') !== null;

        if ($saveRequested) {
            $saveResult = $this->handleSave($infoModel);
            if ($saveResult['success']) {
                $alert = ['type' => 'success', 'message' => 'Salvo com sucesso!'];
                $infoMap = $this->loadInfoMap($infoModel);
            } else {
                $alert = ['type' => 'danger', 'message' => 'Erro: ' . $saveResult['message']];
            }
        }
        if ($method === 'POST' && $this->request->getPost('save_removal_reason') !== null) {
            $alert = $this->handleRemovalReasonSave($date);
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Data invalida.';
        }

        $rows = [];
        $newVmMap = [];
        if ($error === null) {
            $rows = $this->loadInventoryRows($date, '', $infoMap, $error);
            $rows = $this->appendRemovedInventoryRows($rows, $date, '', $infoMap, $error);
            $rows = $this->rowFilter()->migrable($rows);
            $newVmMap = $this->findNewVmsForDateFromDb($date);
        }

        if ($error === null && $exportRequested) {
            return $this->exportCsv($rows, $date);
        }

        return view('reports/detail_vm_migraveis', [
            'date' => $date,
            'rows' => $rows,
            'alert' => $alert,
            'error' => $error,
            'newVmMap' => $newVmMap,
        ] + $this->hostCatalogViewData());
    }

    public function appliancesTodos()
    {
        $db = db_connect();
        $rows = $db->query(
            "SELECT inv.reference_date, COUNT(*) AS vm_count
             FROM rvtools_vm_inventory inv
             INNER JOIN hosts_info info ON info.vm = inv.vm AND info.app = 1
             WHERE inv.included_in_reports = TRUE
             GROUP BY inv.reference_date
            ORDER BY inv.reference_date DESC"
        )->getResultArray();

        if ($this->request->getGet('export') === 'csv') {
            $exportDate = $this->selectedExportDate();
            $exportRows = $this->rowFilter()->summariesForDate($rows, $exportDate);

            return $this->exportSummaryCsv(
                'RVScope_appliances_todos' . ($exportDate !== '' ? '_' . $exportDate : '') . '.csv',
                ['Data', 'Escopo', 'Quantidade de VMs'],
                array_map(static function (array $row): array {
                    return [
                        $row['reference_date'] ?? '',
                        'Todos',
                        (int) ($row['vm_count'] ?? 0),
                    ];
                }, $exportRows)
            );
        }

        return view('reports/appliances_todos', [
            'days' => array_map(static function (array $row): array {
                return [
                    'reference_date' => $row['reference_date'],
                    'vm_count' => (int) ($row['vm_count'] ?? 0),
                ];
            }, $rows),
        ]);
    }

    public function appliances()
    {
        $legacyOnly = $this->request->getGet('legacy') === '1';
        $db = db_connect();
        $sql = "SELECT inv.reference_date,
                       COALESCE(NULLIF(TRIM(info.gerencia), ''), 'Sem registro') AS gerencia,
                       COUNT(*) AS vm_count
                FROM rvtools_vm_inventory inv
                INNER JOIN hosts_info info ON info.vm = inv.vm AND info.app = 1
                WHERE inv.included_in_reports = TRUE";
        if ($legacyOnly) {
            $sql .= " AND info.leg = 1";
        }
        $sql .= " GROUP BY inv.reference_date, COALESCE(NULLIF(TRIM(info.gerencia), ''), 'Sem registro')
                  ORDER BY inv.reference_date DESC, gerencia ASC";
        $rows = $db->query($sql)->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $date = $row['reference_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'reference_date' => $date,
                    'items' => [],
                    'total' => 0,
                ];
            }

            $row['vm_count'] = (int) ($row['vm_count'] ?? 0);
            $grouped[$date]['items'][] = $row;
            $grouped[$date]['total'] += $row['vm_count'];
        }

        if ($this->request->getGet('export') === 'csv') {
            $exportDate = $this->selectedExportDate();
            $exportRows = $this->rowFilter()->summariesForDate($rows, $exportDate);

            return $this->exportSummaryCsv(
                'RVScope_appliances_por_gerencia' . ($legacyOnly ? '_legados' : '') . ($exportDate !== '' ? '_' . $exportDate : '') . '.csv',
                ['Data', 'Gerencia', 'Quantidade de VMs', 'Legados'],
                array_map(static function (array $row) use ($legacyOnly): array {
                    return [
                        $row['reference_date'] ?? '',
                        $row['gerencia'] ?? 'Sem registro',
                        (int) ($row['vm_count'] ?? 0),
                        $legacyOnly ? 'Sim' : 'Nao',
                    ];
                }, $exportRows)
            );
        }

        return view('reports/appliances', [
            'days' => array_values($grouped),
            'legacyOnly' => $legacyOnly,
        ]);
    }

    public function appliancesDetail()
    {
        $date = trim((string) ($this->request->getGet('date') ?? ''));
        if ($date === '') {
            $date = trim((string) ($this->request->getPost('date') ?? ''));
        }

        $gerencia = trim((string) ($this->request->getGet('gerencia') ?? ''));
        if ($gerencia === '') {
            $gerencia = trim((string) ($this->request->getPost('gerencia_filter') ?? ''));
        }
        if ($gerencia === '') {
            $gerencia = trim((string) ($this->request->getPost('gerencia') ?? ''));
        }
        $legacyOnly = $this->request->getGet('legacy') === '1'
            || $this->request->getPost('legacy_filter') === '1';
        $allGerencias = $gerencia === '';

        $alert = null;
        $error = null;

        $infoModel = new HostInfoModel();
        $infoMap = $this->loadInfoMap($infoModel);

        $method = strtoupper($this->request->getMethod());
        $saveRequested = $method === 'POST' && $this->request->getPost('save_info') !== null;
        $exportRequested = $method === 'POST' && $this->request->getPost('export') !== null;

        if ($saveRequested) {
            $saveResult = $this->handleSave($infoModel);
            if ($saveResult['success']) {
                $alert = ['type' => 'success', 'message' => 'Salvo com sucesso!'];
                $infoMap = $this->loadInfoMap($infoModel);
            } else {
                $alert = ['type' => 'danger', 'message' => 'Erro: ' . $saveResult['message']];
            }
        }
        if ($method === 'POST' && $this->request->getPost('save_removal_reason') !== null) {
            $alert = $this->handleRemovalReasonSave($date);
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Data invalida.';
        }

        $rows = [];
        $newVmMap = [];
        if ($error === null) {
            $rows = $this->loadInventoryRows($date, '', $infoMap, $error);
            $rows = $this->appendRemovedInventoryRows($rows, $date, '', $infoMap, $error);
            $rows = $this->rowFilter()->appliances($rows);
            if ($legacyOnly) {
                $rows = $this->rowFilter()->legacy($rows);
            }
            if (! $allGerencias) {
                $rows = $this->rowFilter()->byManagementUnit($rows, $gerencia);
            }
            $newVmMap = $this->findNewVmsForDateFromDb($date);
        }

        if ($error === null && $exportRequested) {
            return $this->exportCsv($rows, $date);
        }

        return view('reports/detail_appliances', [
            'date' => $date,
            'gerencia' => $this->rowFilter()->normalizeManagementUnit($gerencia),
            'rows' => $rows,
            'alert' => $alert,
            'error' => $error,
            'newVmMap' => $newVmMap,
            'legacyOnly' => $legacyOnly,
            'allGerencias' => $allGerencias,
        ] + $this->hostCatalogViewData());
    }

    public function detail()
    {
        $date = trim((string) ($this->request->getGet('date') ?? ''));
        if ($date === '') {
            $date = trim((string) ($this->request->getPost('date') ?? ''));
        }

        $osName = trim((string) ($this->request->getGet('os') ?? ''));
        if ($osName === '') {
            $osName = trim((string) ($this->request->getPost('os') ?? ''));
        }

        $alert = null;
        $error = null;

        $infoModel = new HostInfoModel();
        $infoMap = $this->loadInfoMap($infoModel);

        $method = strtoupper($this->request->getMethod());
        $saveRequested = $method === 'POST' && $this->request->getPost('save_info') !== null;
        $exportRequested = $method === 'POST' && $this->request->getPost('export') !== null;

        if ($saveRequested) {
            $saveResult = $this->handleSave($infoModel);
            if ($saveResult['success']) {
                $alert = ['type' => 'success', 'message' => 'Salvo com sucesso!'];
                $infoMap = $this->loadInfoMap($infoModel);
            } else {
                $alert = ['type' => 'danger', 'message' => 'Erro: ' . $saveResult['message']];
            }
        }
        if ($method === 'POST' && $this->request->getPost('save_removal_reason') !== null) {
            $alert = $this->handleRemovalReasonSave($date);
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Data invalida.';
        }

        $rows = [];
        $newVmMap = [];
        if ($error === null) {
            $rows = $this->loadInventoryRows($date, $osName, $infoMap, $error);
            $rows = $this->appendRemovedInventoryRows($rows, $date, $osName, $infoMap, $error);
            $newVmMap = $this->findNewVmsForDateFromDb($date);
        }

        if ($error === null && $exportRequested) {
            return $this->exportCsv($rows, $date);
        }

        return view('reports/detail', [
            'date' => $date,
            'osName' => $osName,
            'rows' => $rows,
            'alert' => $alert,
            'error' => $error,
            'newVmMap' => $newVmMap,
        ] + $this->hostCatalogViewData());
    }

    private function findNewVmsForDateFromDb(string $date): array
    {
        $previousDate = $this->findPreviousDate($date);
        if ($previousDate === null) {
            return [];
        }

        $db = db_connect();
        $builder = $db->table('rvtools_vm_inventory as cur');
        $builder->select('cur.vm');
        $builder->join(
            'rvtools_vm_inventory as prev',
            'prev.vm = cur.vm
             AND prev.reference_date = ' . $db->escape($previousDate) . '
             AND prev.included_in_reports = TRUE',
            'left',
            false
        );
        $builder->where('cur.reference_date', $date);
        $builder->where('cur.included_in_reports', true);
        $builder->where('prev.vm IS NULL', null, false);

        $rows = $builder->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $vm = $row['vm'] ?? '';
            if ($vm !== '') {
                $map[$vm] = true;
            }
        }

        return $map;
    }

    private function renderVmTodos(array $context)
    {
        $model = new RvtoolsOsSummaryModel();

        $rows = $model->orderBy('reference_date', 'DESC')
            ->orderBy('os_name', 'ASC')
            ->findAll();
        $vmChangeCounts = $this->findVmChangeCountsByDateAndOs();

        $grouped = [];
        foreach ($rows as $row) {
            $date = $row['reference_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'reference_date' => $date,
                    'items' => [],
                    'total' => 0,
                    'new_vm_total' => array_sum($vmChangeCounts['new'][$date] ?? []),
                    'removed_vm_total' => array_sum($vmChangeCounts['removed'][$date] ?? []),
                ];
            }

            $osName = (string) ($row['os_name'] ?? '');
            $row['new_vm_count'] = $vmChangeCounts['new'][$date][$osName] ?? 0;
            $row['removed_vm_count'] = $vmChangeCounts['removed'][$date][$osName] ?? 0;
            $row['has_new'] = $row['new_vm_count'] > 0;
            $grouped[$date]['items'][] = $row;
            $grouped[$date]['total'] += (int) $row['vm_count'];
        }

        foreach ($vmChangeCounts['removed'] as $date => $osCounts) {
            if (!isset($grouped[$date])) {
                continue;
            }

            $listedOsNames = array_fill_keys(
                array_map(
                    static fn(array $item): string => (string) ($item['os_name'] ?? ''),
                    $grouped[$date]['items'],
                ),
                true,
            );
            foreach ($osCounts as $osName => $removedCount) {
                if (isset($listedOsNames[$osName])) {
                    continue;
                }

                $grouped[$date]['items'][] = [
                    'reference_date' => $date,
                    'os_name' => $osName,
                    'vm_count' => 0,
                    'new_vm_count' => 0,
                    'removed_vm_count' => $removedCount,
                    'has_new' => false,
                ];
            }

            usort(
                $grouped[$date]['items'],
                static fn(array $left, array $right): int => strcasecmp(
                    (string) ($left['os_name'] ?? ''),
                    (string) ($right['os_name'] ?? ''),
                ),
            );
        }

        if ($this->request->getGet('export') === 'csv') {
            $exportDate = $this->selectedExportDate();
            $exportRows = $this->rowFilter()->summariesForDate($rows, $exportDate);

            return $this->exportSummaryCsv(
                'RVScope_vm_por_sistema_operacional' . ($exportDate !== '' ? '_' . $exportDate : '') . '.csv',
                [
                    'Data',
                    'Sistema Operacional',
                    'Quantidade de VMs',
                    'Quantidade de VMs novas',
                    'Quantidade de VMs removidas',
                ],
                array_map(static function (array $row) use ($vmChangeCounts): array {
                    $date = (string) ($row['reference_date'] ?? '');
                    $osName = (string) ($row['os_name'] ?? '');

                    return [
                        $date,
                        $osName,
                        (int) ($row['vm_count'] ?? 0),
                        $vmChangeCounts['new'][$date][$osName] ?? 0,
                        $vmChangeCounts['removed'][$date][$osName] ?? 0,
                    ];
                }, $exportRows)
            );
        }

        return view('reports/index', $context + [
            'days' => array_values($grouped),
        ]);
    }

    private function findVmChangeCountsByDateAndOs(): array
    {
        $sql = <<<'SQL'
            WITH inventory_dates AS (
                SELECT reference_date,
                       LAG(reference_date) OVER (ORDER BY reference_date) AS previous_date
                FROM (
                    SELECT DISTINCT reference_date
                    FROM rvtools_vm_inventory
                    WHERE included_in_reports = TRUE
                ) dates
            ),
            inventory_changes AS (
                SELECT current_inventory.reference_date,
                       current_inventory.os_name,
                       'new' AS change_type
                FROM inventory_dates
                INNER JOIN rvtools_vm_inventory current_inventory
                    ON current_inventory.reference_date = inventory_dates.reference_date
                   AND current_inventory.included_in_reports = TRUE
                LEFT JOIN rvtools_vm_inventory previous_inventory
                    ON previous_inventory.reference_date = inventory_dates.previous_date
                   AND previous_inventory.vm = current_inventory.vm
                   AND previous_inventory.included_in_reports = TRUE
                WHERE inventory_dates.previous_date IS NOT NULL
                  AND previous_inventory.id IS NULL

                UNION ALL

                SELECT inventory_dates.reference_date,
                       previous_inventory.os_name,
                       'removed' AS change_type
                FROM inventory_dates
                INNER JOIN rvtools_vm_inventory previous_inventory
                    ON previous_inventory.reference_date = inventory_dates.previous_date
                   AND previous_inventory.included_in_reports = TRUE
                LEFT JOIN rvtools_vm_inventory current_inventory
                    ON current_inventory.reference_date = inventory_dates.reference_date
                   AND current_inventory.vm = previous_inventory.vm
                   AND current_inventory.included_in_reports = TRUE
                WHERE inventory_dates.previous_date IS NOT NULL
                  AND current_inventory.id IS NULL
            )
            SELECT reference_date,
                   os_name,
                   change_type,
                   COUNT(*) AS vm_count
            FROM inventory_changes
            GROUP BY reference_date, os_name, change_type
            SQL;

        $rows = db_connect()->query($sql)->getResultArray();
        $counts = [
            'new' => [],
            'removed' => [],
        ];
        foreach ($rows as $row) {
            $date = (string) ($row['reference_date'] ?? '');
            $osName = (string) ($row['os_name'] ?? '');
            $changeType = (string) ($row['change_type'] ?? '');
            if ($date === '' || $osName === '' || !isset($counts[$changeType])) {
                continue;
            }

            $counts[$changeType][$date][$osName] = (int) ($row['vm_count'] ?? 0);
        }

        return $counts;
    }

    private function selectedExportDate(): string
    {
        $date = trim((string) ($this->request->getGet('date') ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    private function findPreviousDate(string $date): ?string
    {
        $model = new RvtoolsOsSummaryModel();
        $row = $model->select('reference_date')
            ->where('reference_date <', $date)
            ->orderBy('reference_date', 'DESC')
            ->first();

        $previous = $row['reference_date'] ?? null;
        return $previous ?: null;
    }

    private function handleSave(HostInfoModel $infoModel): array
    {
        if (! UserAuthorization::canEditHosts()) {
            return [
                'success' => false,
                'message' => 'Seu perfil permite apenas consultar as informações dos hosts.',
            ];
        }

        $vm = trim((string) ($this->request->getPost('vm') ?? ''));
        if ($vm === '') {
            return ['success' => false, 'message' => 'Nome da VM vazio.'];
        }

        $desc = trim((string) ($this->request->getPost('desc') ?? ''));
        $managementUnitId = (int) ($this->request->getPost('management_unit_id') ?? 0);
        $technicalResponsibleId = (int) ($this->request->getPost('technical_responsible_id') ?? 0);
        $gerencia = 'Sem registro';
        $owner = 'Sem registro';
        $contract = trim((string) ($this->request->getPost('contract') ?? ''));
        $conv = trim((string) ($this->request->getPost('conv') ?? ''));
        $creationDate = trim((string) ($this->request->getPost('creation_date') ?? ''));
        $osLastUpdateDate = trim((string) ($this->request->getPost('os_last_update_date') ?? ''));
        $migrationTarget = strtolower(trim(
            (string) ($this->request->getPost('migration_target') ?? 'none')
        ));
        $allowedMigrationTargets = ['none', 'other_host', 'openshift'];
        if (! in_array($migrationTarget, $allowedMigrationTargets, true)) {
            $migrationTarget = 'none';
        }
        $worker = strtolower(trim((string) ($this->request->getPost('worker') ?? 'none')));
        $allowedWorkers = ['none', 'openshift', 'rancher'];
        if (! in_array($worker, $allowedWorkers, true)) {
            $worker = 'none';
        }

        $desc = str_replace(';', ',', $desc);
        $conv = str_replace(';', ',', $conv);

        if (mb_strlen($contract) > 500) {
            return ['success' => false, 'message' => 'O campo Contrato deve ter no máximo 500 caracteres.'];
        }

        if ($managementUnitId > 0) {
            $managementUnit = (new ManagementUnitModel())->find($managementUnitId);
            if (! is_array($managementUnit)) {
                return ['success' => false, 'message' => 'Gerência selecionada não encontrada.'];
            }
            $gerencia = (string) ($managementUnit['name'] ?? 'Sem registro');
        } else {
            $managementUnitId = 0;
        }

        if ($technicalResponsibleId > 0) {
            if ($managementUnitId <= 0) {
                return ['success' => false, 'message' => 'Selecione uma gerência antes do responsável técnico.'];
            }
            $relationshipExists = (new ManagementUnitTechnicalResponsibleModel())
                ->where('management_unit_id', $managementUnitId)
                ->where('technical_responsible_id', $technicalResponsibleId)
                ->countAllResults() > 0;
            $technicalResponsible = (new TechnicalResponsibleModel())->find($technicalResponsibleId);
            if (! $relationshipExists || ! is_array($technicalResponsible)) {
                return [
                    'success' => false,
                    'message' => 'O responsável técnico não está vinculado à gerência selecionada.',
                ];
            }
            $owner = (string) ($technicalResponsible['name'] ?? 'Sem registro');
        } else {
            $technicalResponsibleId = 0;
        }

        if ($creationDate !== '') {
            $dt = DateTime::createFromFormat('d/m/Y', $creationDate);
            if ($dt === false) {
                return ['success' => false, 'message' => 'Data de criacao invalida (use dd/mm/aaaa).'];
            }
            $creationDate = $dt->format('d/m/Y');
        }
        if ($osLastUpdateDate !== '') {
            $dt = DateTime::createFromFormat('!Y-m-d', $osLastUpdateDate);
            $dateErrors = DateTime::getLastErrors();
            if ($dt === false
                || ($dateErrors !== false
                    && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $dt->format('Y-m-d') !== $osLastUpdateDate) {
                return ['success' => false, 'message' => 'Data da última atualização do SO inválida.'];
            }
            $osLastUpdateDate = $dt->format('Y-m-d');
        }

        $data = [
            'vm' => $vm,
            'desc' => $desc,
            'gerencia' => $gerencia,
            'management_unit_id' => $managementUnitId > 0 ? $managementUnitId : null,
            'owner' => $owner,
            'technical_responsible_id' => $technicalResponsibleId > 0 ? $technicalResponsibleId : null,
            'contract' => $contract,
            'conv' => $conv,
            'leg' => $this->request->getPost('legacy') ? 1 : 0,
            'mig' => $migrationTarget !== 'none' ? 1 : 0,
            'migration_target' => $migrationTarget,
            'app' => $this->request->getPost('appliance') ? 1 : 0,
            'worker' => $worker,
            'creation_date' => $creationDate,
            'os_last_update_date' => $osLastUpdateDate !== '' ? $osLastUpdateDate : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $infoModel->save($data);
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return ['success' => true, 'message' => ''];
    }

    private function loadInfoMap(HostInfoModel $infoModel): array
    {
        $rows = $infoModel->select(
            'vm, desc, gerencia, management_unit_id, owner, technical_responsible_id, contract, '
            . 'conv, leg, mig, migration_target, app, worker, '
            . 'creation_date, os_last_update_date'
        )
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $vm = $row['vm'] ?? '';
            if ($vm === '') {
                continue;
            }
            $isMigrable = (int) ($row['mig'] ?? 0) === 1;
            $migrationTarget = (string) ($row['migration_target'] ?? '');
            if (! in_array($migrationTarget, ['none', 'other_host', 'openshift'], true)) {
                $migrationTarget = $isMigrable ? 'other_host' : 'none';
            }
            $map[$vm] = [
                'desc' => $row['desc'] ?? 'Sem registro',
                'gerencia' => $row['gerencia'] ?? 'Sem registro',
                'management_unit_id' => (int) ($row['management_unit_id'] ?? 0),
                'owner' => $row['owner'] ?? 'Sem registro',
                'technical_responsible_id' => (int) ($row['technical_responsible_id'] ?? 0),
                'contract' => trim((string) ($row['contract'] ?? '')),
                'conv' => $row['conv'] ?? 'Nao informado',
                'leg' => ((int) ($row['leg'] ?? 0)) ? '1' : '0',
                'mig' => $isMigrable ? '1' : '0',
                'migration_target' => $migrationTarget,
                'app' => ((int) ($row['app'] ?? 0)) ? '1' : '0',
                'worker' => $row['worker'] ?? 'none',
                'creation_date' => trim((string) ($row['creation_date'] ?? '')),
                'os_last_update_date' => trim((string) ($row['os_last_update_date'] ?? '')),
            ];
        }

        return $map;
    }

    private function loadInventoryRows(
        string $date,
        string $osFilter,
        array $infoMap,
        ?string &$error,
    ): array {
        $inventoryModel = new RvtoolsVmInventoryModel();
        $inventoryModel
            ->select(
                'vm, dns_name, primary_ip, os_name, os_name_raw, creation_date_raw, '
                . 'annotation, source_filename'
            )
            ->where('reference_date', $date)
            ->where('included_in_reports', true)
            ->orderBy('vm', 'ASC');

        if ($osFilter !== '') {
            $inventoryModel->where('os_name', $osFilter);
        }

        $inventoryRows = $inventoryModel->findAll();
        if ($inventoryRows === []) {
            $error = 'Nenhum dado de inventário encontrado para esta data.';
            return [];
        }

        $hasCompleteSnapshot = false;
        foreach ($inventoryRows as $inventoryRow) {
            if (trim((string) ($inventoryRow['source_filename'] ?? '')) !== '') {
                $hasCompleteSnapshot = true;
                break;
            }
        }

        if (! $hasCompleteSnapshot) {
            $csvPath = $this->findCsvPath($date);
            if ($csvPath !== null) {
                $rows = $this->parseCsvRows($csvPath, $osFilter, $infoMap, $error);
                return $this->rowFilter()->presentInInventory($rows, $date);
            }

            $error = 'Os detalhes desta data ainda não foram migrados para o banco de dados.';
            return [];
        }

        $rows = [];
        foreach ($inventoryRows as $inventoryRow) {
            $vm = trim((string) ($inventoryRow['vm'] ?? ''));
            if ($vm === '') {
                continue;
            }

            $info = $infoMap[$vm] ?? $this->defaultInfo();
            $rows[] = [
                'vm' => $vm,
                'dns' => (string) ($inventoryRow['dns_name'] ?? ''),
                'ip' => (string) ($inventoryRow['primary_ip'] ?? ''),
                'os' => (string) (
                    ($inventoryRow['os_name_raw'] ?? '') ?: ($inventoryRow['os_name'] ?? '')
                ),
                'creation' => $this->resolveCreationDate(
                    $vm,
                    (string) ($inventoryRow['creation_date_raw'] ?? ''),
                    $infoMap,
                ),
                'annotation' => (string) ($inventoryRow['annotation'] ?? ''),
                'info' => $info,
            ];
        }

        return $rows;
    }

    private function appendRemovedInventoryRows(
        array $rows,
        string $date,
        string $osFilter,
        array $infoMap,
        ?string &$error,
    ): array {
        $previousDate = $this->findPreviousDate($date);
        if ($previousDate === null) {
            return $rows;
        }

        $db = db_connect();
        $builder = $db->table('rvtools_vm_inventory as previous_inventory');
        $builder->select(
            'previous_inventory.vm, previous_inventory.dns_name, '
            . 'previous_inventory.primary_ip, previous_inventory.os_name, '
            . 'previous_inventory.os_name_raw, previous_inventory.creation_date_raw, '
            . 'previous_inventory.annotation'
        );
        $builder->join(
            'rvtools_vm_inventory as current_inventory',
            'current_inventory.vm = previous_inventory.vm
             AND current_inventory.reference_date = ' . $db->escape($date) . '
             AND current_inventory.included_in_reports = TRUE',
            'left',
            false,
        );
        $builder->where('previous_inventory.reference_date', $previousDate);
        $builder->where('previous_inventory.included_in_reports', true);
        $builder->where('current_inventory.id IS NULL', null, false);
        if ($osFilter !== '') {
            $builder->where('previous_inventory.os_name', $osFilter);
        }
        $builder->orderBy('previous_inventory.vm', 'ASC');

        $removalReasons = (new HostRemovalReasonModel())->reasonsForDate($date);
        $removedRows = [];
        foreach ($builder->get()->getResultArray() as $inventoryRow) {
            $vm = trim((string) ($inventoryRow['vm'] ?? ''));
            if ($vm === '') {
                continue;
            }

            $info = $infoMap[$vm] ?? $this->defaultInfo();
            $removedRows[] = [
                'vm' => $vm,
                'dns' => (string) ($inventoryRow['dns_name'] ?? ''),
                'ip' => (string) ($inventoryRow['primary_ip'] ?? ''),
                'os' => (string) (
                    ($inventoryRow['os_name_raw'] ?? '') ?: ($inventoryRow['os_name'] ?? '')
                ),
                'creation' => $this->resolveCreationDate(
                    $vm,
                    (string) ($inventoryRow['creation_date_raw'] ?? ''),
                    $infoMap,
                ),
                'annotation' => (string) ($inventoryRow['annotation'] ?? ''),
                'info' => $info,
                'is_removed' => true,
                'removal_reason' => $removalReasons[$vm] ?? '',
            ];
        }

        if ($removedRows !== [] && $rows === []) {
            $error = null;
        }

        return array_merge($rows, $removedRows);
    }

    private function handleRemovalReasonSave(string $date): array
    {
        if (! UserAuthorization::canEditHosts()) {
            return [
                'type' => 'danger',
                'message' => 'Seu perfil permite apenas consultar o motivo da remoção.',
            ];
        }

        $vm = trim((string) ($this->request->getPost('vm') ?? ''));
        $reason = trim((string) ($this->request->getPost('removal_reason') ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $vm === '') {
            return ['type' => 'danger', 'message' => 'Host ou data de remoção inválidos.'];
        }
        if ($reason === '') {
            return ['type' => 'danger', 'message' => 'Informe o motivo da remoção.'];
        }
        if (mb_strlen($reason) > 2000) {
            return ['type' => 'danger', 'message' => 'O motivo deve ter no máximo 2000 caracteres.'];
        }
        if (! $this->isVmRemovedForDate($date, $vm)) {
            return ['type' => 'danger', 'message' => 'O host informado não está removido nesta data.'];
        }

        try {
            (new HostRemovalReasonModel())->setReason(
                $date,
                $vm,
                $reason,
                (string) (session('auth_username') ?: session('admin_username') ?: ''),
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao salvar motivo de remoção: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return ['type' => 'danger', 'message' => 'Não foi possível salvar o motivo da remoção.'];
        }

        return ['type' => 'success', 'message' => 'Motivo da remoção salvo com sucesso.'];
    }

    private function isVmRemovedForDate(string $date, string $vm): bool
    {
        $previousDate = $this->findPreviousDate($date);
        if ($previousDate === null) {
            return false;
        }

        $db = db_connect();
        $previousExists = $db->table('rvtools_vm_inventory')
            ->where('reference_date', $previousDate)
            ->where('vm', $vm)
            ->where('included_in_reports', true)
            ->countAllResults() > 0;
        $currentExists = $db->table('rvtools_vm_inventory')
            ->where('reference_date', $date)
            ->where('vm', $vm)
            ->where('included_in_reports', true)
            ->countAllResults() > 0;

        return $previousExists && ! $currentExists;
    }

    private function findCsvPath(string $date): ?string
    {
        $importPath = $this->resolveImportPath();
        $pattern = rtrim($importPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'RVTools_ExportvInfo2csv_'
            . $date
            . '_*.csv';

        $files = glob($pattern);
        if (!$files) {
            return null;
        }

        return $files[0];
    }

    private function resolveImportPath(): string
    {
        $config = config('Rvtools');
        if ($config instanceof RvtoolsConfig) {
            $configuredPath = $config->importPath;
        } else {
            $configuredPath = '/app/imports';
        }

        $candidates = [
            $configuredPath,
            rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'imports',
            rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'imports',
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_dir($path)) {
                return $path;
            }
        }

        return $configuredPath;
    }

    private function parseCsvRows(string $csvPath, string $osFilter, array $infoMap, ?string &$error): array
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            $error = 'Erro ao abrir CSV.';
            return [];
        }

        $header = fgetcsv($handle, 0, ';', '"', '');
        if ($header === false) {
            fclose($handle);
            $error = 'CSV sem cabecalho.';
            return [];
        }

        $header = array_map([$this, 'normalizeHeaderValue'], $header);
        $index = array_flip($header);

        $idxVM = $index['VM'] ?? null;
        $idxPS = $index['Powerstate'] ?? null;
        $idxDNS = $index['DNS Name'] ?? null;
        $idxIP = $index['Primary IP Address'] ?? ($index['IP Address'] ?? null);
        $idxOS = $index['OS according to the VMware Tools'] ?? null;
        $idxCD = $index['Creation date'] ?? null;
        $idxAN = $index['Annotation'] ?? null;

        if ($idxVM === null || $idxPS === null || $idxDNS === null || $idxOS === null) {
            fclose($handle);
            $error = 'Colunas obrigatorias nao encontradas no CSV.';
            return [];
        }

        $rows = [];
        while (($line = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            if (($line[$idxPS] ?? '') !== 'poweredOn') {
                continue;
            }

            $osValue = (string) ($line[$idxOS] ?? '');
            if (! $this->osMatch($osValue, $osFilter)) {
                continue;
            }

            $vm = (string) ($line[$idxVM] ?? '');
            $dns = (string) ($line[$idxDNS] ?? '');
            $ip = $idxIP !== null ? (string) ($line[$idxIP] ?? '') : '';
            $creationRaw = $idxCD !== null ? (string) ($line[$idxCD] ?? '') : '';
            $annotation = $idxAN !== null ? trim((string) ($line[$idxAN] ?? '')) : '';

            $vm = $this->sanitizeUtf8($vm);
            $dns = $this->sanitizeUtf8($dns);
            $ip = $this->sanitizeUtf8($ip);
            $annotation = $this->sanitizeUtf8($annotation);

            $info = $infoMap[$vm] ?? $this->defaultInfo();
            $creation = $this->resolveCreationDate($vm, $creationRaw, $infoMap);

            $rows[] = [
                'vm' => $vm,
                'dns' => $dns,
                'ip' => $ip,
                'os' => $osValue,
                'creation' => $creation,
                'annotation' => $annotation,
                'info' => $info,
            ];
        }

        fclose($handle);
        return $rows;
    }

    private function exportCsv(array $rows, string $date)
    {
        $filename = 'RVScope_' . $date . '.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            '#',
            'Name VMWare',
            'DNS Hostname',
            'IP',
            'OS VMTools',
            'Creation',
            "Descri\xc3\xa7\xc3\xa3o",
            "Respons\xc3\xa1vel T\xc3\xa9cnico",
            'Contrato',
            'Conversando',
            'Legado',
            "Migr\xc3\xa1vel",
            'Migração',
            'Appliance',
            'Worker',
            'Última atualização do SO',
            'Status',
        ], ';', '"', '');

        $counter = 1;
        foreach ($rows as $row) {
            $info = $row['info'];
            fputcsv($handle, [
                $counter++,
                $row['vm'],
                $row['dns'],
                $row['ip'] ?? '',
                $row['os'],
                $row['creation'],
                $info['desc'] ?? 'Sem registro',
                $info['owner'] ?? 'Sem registro',
                $info['contract'] ?? '',
                $info['conv'] ?? 'Nao informado',
                $info['leg'] ?? '0',
                $info['mig'] ?? '0',
                $this->migrationTargetLabel((string) ($info['migration_target'] ?? 'none')),
                $info['app'] ?? '0',
                $info['worker'] ?? 'none',
                $info['os_last_update_date'] ?? '',
                !empty($row['is_removed']) ? 'Removido' : 'Ativo',
            ], ';', '"', '');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename=' . $filename)
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($csvContent);
    }

    private function exportSummaryCsv(string $filename, array $header, array $rows)
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header, ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename=' . $filename)
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($csvContent);
    }

    private function resolveCreationDate(string $vm, string $csvRaw, array $infoMap): string
    {
        if ($vm !== '' && isset($infoMap[$vm])) {
            $dbValue = trim((string) ($infoMap[$vm]['creation_date'] ?? ''));
            if ($dbValue !== '') {
                return $dbValue;
            }
        }

        return $this->formatCreationDate($csvRaw);
    }

    private function formatCreationDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $dt = DateTime::createFromFormat('Y/m/d H:i:s', $raw);
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw)
                ?: DateTime::createFromFormat('Y/m/d', $raw)
                ?: DateTime::createFromFormat('Y-m-d', $raw);
        }

        return $dt ? $dt->format('d/m/Y') : '';
    }

    private function osMatch(string $os, string $filter): bool
    {
        $filter = trim($filter);
        if ($filter === '') {
            return true;
        }

        if (strcasecmp($filter, 'Other') === 0) {
            return stripos($os, 'Other') !== false
                || stripos($os, 'SUSE') !== false
                || stripos($os, 'FreeB') !== false;
        }

        return stripos($os, $filter) !== false;
    }

    private function normalizeHeaderValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value == '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }

    private function defaultInfo(): array
    {
        return [
            'desc' => 'Sem registro',
            'gerencia' => 'Sem registro',
            'management_unit_id' => 0,
            'owner' => 'Sem registro',
            'technical_responsible_id' => 0,
            'contract' => '',
            'conv' => 'Nao informado',
            'leg' => '0',
            'mig' => '0',
            'migration_target' => 'none',
            'app' => '0',
            'worker' => 'none',
            'creation_date' => '',
            'os_last_update_date' => '',
        ];
    }

    private function migrationTargetLabel(string $target): string
    {
        return match ($target) {
            'other_host' => 'Outro Host',
            'openshift' => 'OpenShift',
            default => 'Não migrável',
        };
    }

    private function hostCatalogViewData(): array
    {
        $managementUnits = (new ManagementUnitModel())->orderBy('name', 'ASC')->findAll();
        $technicalResponsibles = (new TechnicalResponsibleModel())->orderBy('name', 'ASC')->findAll();
        $relationships = (new ManagementUnitTechnicalResponsibleModel())->findAll();

        $responsiblesById = [];
        foreach ($technicalResponsibles as $responsible) {
            $responsibleId = (int) ($responsible['id'] ?? 0);
            if ($responsibleId > 0) {
                $responsiblesById[$responsibleId] = [
                    'id' => $responsibleId,
                    'name' => (string) ($responsible['name'] ?? ''),
                ];
            }
        }

        $responsiblesByManagementUnit = [];
        foreach ($relationships as $relationship) {
            $managementId = (int) ($relationship['management_unit_id'] ?? 0);
            $responsibleId = (int) ($relationship['technical_responsible_id'] ?? 0);
            if ($managementId > 0 && isset($responsiblesById[$responsibleId])) {
                $responsiblesByManagementUnit[$managementId][] = $responsiblesById[$responsibleId];
            }
        }
        foreach ($responsiblesByManagementUnit as &$responsibles) {
            usort($responsibles, static function (array $left, array $right): int {
                return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            });
        }
        unset($responsibles);

        return [
            'managementUnits' => $managementUnits,
            'technicalResponsiblesByManagementUnit' => $responsiblesByManagementUnit,
        ];
    }

    private function rowFilter(): ReportRowFilter
    {
        return $this->reportRowFilter ??= new ReportRowFilter();
    }

}
