<?php

namespace App\Controllers;

use App\Models\HostInfoModel;
use App\Models\RvtoolsOsSummaryModel;
use CodeIgniter\Controller;
use Config\Rvtools as RvtoolsConfig;
use DateTime;

class ReportController extends Controller
{
    public function index()
    {
        $model = new RvtoolsOsSummaryModel();

        $rows = $model->orderBy('reference_date', 'DESC')
            ->orderBy('os_name', 'ASC')
            ->findAll();

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

            $row['has_new'] = $this->normalizeBool($row['has_new'] ?? false);
            $grouped[$date]['items'][] = $row;
            $grouped[$date]['total'] += (int) $row['vm_count'];
        }

        return view('reports/index', [
            'days' => array_values($grouped),
        ]);
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

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Data invalida.';
        }

        $csvPath = null;
        if ($error === null) {
            $csvPath = $this->findCsvPath($date);
            if ($csvPath === null) {
                $error = 'Nenhum arquivo CSV encontrado para esta data.';
            }
        }

        $rows = [];
        $newVmMap = [];
        if ($error === null && $csvPath !== null) {
            $rows = $this->parseCsvRows($csvPath, $osName, $infoMap, $error);
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
        ]);
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
            'prev.vm = cur.vm AND prev.reference_date = ' . $db->escape($previousDate),
            'left',
            false
        );
        $builder->where('cur.reference_date', $date);
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

    private function normalizeBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 't', 'true', 'yes', 'y'], true);
        }

        return false;
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
        $vm = trim((string) ($this->request->getPost('vm') ?? ''));
        if ($vm === '') {
            return ['success' => false, 'message' => 'Nome da VM vazio.'];
        }

        $desc = trim((string) ($this->request->getPost('desc') ?? ''));
        $owner = trim((string) ($this->request->getPost('owner') ?? ''));
        $conv = trim((string) ($this->request->getPost('conv') ?? ''));
        $creationDate = trim((string) ($this->request->getPost('creation_date') ?? ''));
        $worker = strtolower(trim((string) ($this->request->getPost('worker') ?? 'none')));
        $allowedWorkers = ['none', 'openshift', 'rancher'];
        if (! in_array($worker, $allowedWorkers, true)) {
            $worker = 'none';
        }

        $desc = str_replace(';', ',', $desc);
        $owner = str_replace(';', ',', $owner);
        $conv = str_replace(';', ',', $conv);

        if ($creationDate !== '') {
            $dt = DateTime::createFromFormat('d/m/Y', $creationDate);
            if ($dt === false) {
            return ['success' => false, 'message' => 'Data de criacao invalida (use dd/mm/aaaa).'];
            }
            $creationDate = $dt->format('d/m/Y');
        }

        $data = [
            'vm' => $vm,
            'desc' => $desc,
            'owner' => $owner,
            'conv' => $conv,
            'leg' => $this->request->getPost('legacy') ? 1 : 0,
            'mig' => $this->request->getPost('migrable') ? 1 : 0,
            'app' => $this->request->getPost('appliance') ? 1 : 0,
            'worker' => $worker,
            'creation_date' => $creationDate,
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
        $rows = $infoModel->select('vm, desc, owner, conv, leg, mig, app, worker, creation_date')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $vm = $row['vm'] ?? '';
            if ($vm === '') {
                continue;
            }
            $map[$vm] = [
                'desc' => $row['desc'] ?? 'Sem registro',
                'owner' => $row['owner'] ?? 'Sem registro',
                'conv' => $row['conv'] ?? 'Nao informado',
                'leg' => ((int) ($row['leg'] ?? 0)) ? '1' : '0',
                'mig' => ((int) ($row['mig'] ?? 0)) ? '1' : '0',
                'app' => ((int) ($row['app'] ?? 0)) ? '1' : '0',
                'worker' => $row['worker'] ?? 'none',
                'creation_date' => trim((string) ($row['creation_date'] ?? '')),
            ];
        }

        return $map;
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

        $header = fgetcsv($handle, 0, ';');
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
        $idxOS = $index['OS according to the VMware Tools'] ?? null;
        $idxCD = $index['Creation date'] ?? null;
        $idxAN = $index['Annotation'] ?? null;

        if ($idxVM === null || $idxPS === null || $idxDNS === null || $idxOS === null) {
            fclose($handle);
            $error = 'Colunas obrigatorias nao encontradas no CSV.';
            return [];
        }

        $rows = [];
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (($line[$idxPS] ?? '') !== 'poweredOn') {
                continue;
            }

            $osValue = (string) ($line[$idxOS] ?? '');
            if (! $this->osMatch($osValue, $osFilter)) {
                continue;
            }

            $vm = (string) ($line[$idxVM] ?? '');
            $dns = (string) ($line[$idxDNS] ?? '');
            $creationRaw = $idxCD !== null ? (string) ($line[$idxCD] ?? '') : '';
            $annotation = $idxAN !== null ? trim((string) ($line[$idxAN] ?? '')) : '';

            $vm = $this->sanitizeUtf8($vm);
            $dns = $this->sanitizeUtf8($dns);
            $annotation = $this->sanitizeUtf8($annotation);

            $info = $infoMap[$vm] ?? $this->defaultInfo();
            $creation = $this->resolveCreationDate($vm, $creationRaw, $infoMap);

            $rows[] = [
                'vm' => $vm,
                'dns' => $dns,
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
            'OS VMTools',
            'Creation',
            "Descri\xc3\xa7\xc3\xa3o",
            "Respons\xc3\xa1vel",
            'Conversando',
            'Legado',
            "Migr\xc3\xa1vel",
            'Appliance',
        ], ';');

        $counter = 1;
        foreach ($rows as $row) {
            $info = $row['info'];
            fputcsv($handle, [
                $counter++,
                $row['vm'],
                $row['dns'],
                $row['os'],
                $row['creation'],
                $info['desc'] ?? 'Sem registro',
                $info['owner'] ?? 'Sem registro',
                $info['conv'] ?? 'Nao informado',
                $info['leg'] ?? '0',
                $info['mig'] ?? '0',
                $info['app'] ?? '0',
            ], ';');
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
            'owner' => 'Sem registro',
            'conv' => 'Nao informado',
            'leg' => '0',
            'mig' => '0',
            'app' => '0',
            'worker' => 'none',
            'creation_date' => '',
        ];
    }

}
