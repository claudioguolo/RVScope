<?php

namespace App\Libraries;

use App\Models\RvtoolsImportLogModel;
use App\Models\RvtoolsOsSummaryModel;
use App\Models\RvtoolsVmInventoryModel;
use CodeIgniter\Database\BaseConnection;
use Config\Rvtools as RvtoolsConfig;
use RuntimeException;

class RvtoolsImporter
{
    private BaseConnection $db;
    private RvtoolsOsSummaryModel $summaryModel;
    private RvtoolsVmInventoryModel $inventoryModel;
    private RvtoolsImportLogModel $importLogModel;
    private string $importPath;
    private int $osMaxLength;
    private RvtoolsCsvFileInspector $fileInspector;
    private RvtoolsOsFallbackResolver $osFallbackResolver;
    private OperatingSystemInclusionPolicy $operatingSystemPolicy;
    private array $operatingSystemPolicies = [];
    private array $ignoredNormalizedOperatingSystems = [];

    public function __construct(?BaseConnection $db = null, ?RvtoolsConfig $config = null)
    {
        $this->db = $db ?? db_connect();
        $this->summaryModel = new RvtoolsOsSummaryModel($this->db);
        $this->inventoryModel = new RvtoolsVmInventoryModel($this->db);
        $this->importLogModel = new RvtoolsImportLogModel($this->db);
        $this->fileInspector = new RvtoolsCsvFileInspector();
        $this->osFallbackResolver = new RvtoolsOsFallbackResolver();
        $this->operatingSystemPolicy = new OperatingSystemInclusionPolicy();
        $this->operatingSystemPolicies = $this->loadOperatingSystemPolicies();

        $config = $config ?? config('Rvtools');
        $this->importPath = $this->resolveImportPath($config->importPath);
        $this->osMaxLength = $config->osMaxLength;
    }

    public function importAll(): array
    {
        return $this->importDirectory($this->importPath);
    }

    public function inspectBackfill(string $path): array
    {
        return $this->prepareFiles($path, true);
    }

    public function backfill(string $path, ?callable $progress = null): array
    {
        $prepared = $this->prepareFiles($path, true);
        if ($prepared['errors'] !== []) {
            return [
                'import_path' => $path,
                'selected' => count($prepared['files']),
                'processed' => 0,
                'skipped' => 0,
                'errors' => $prepared['errors'],
            ];
        }

        $result = $this->processFiles($path, $prepared['files'], true, $progress);
        $result['incompatible'] = $prepared['incompatible'];

        return $result;
    }

    private function importDirectory(string $path): array
    {
        $prepared = $this->prepareFiles($path, false);
        if ($prepared['errors'] !== []) {
            return [
                'import_path' => $path,
                'selected' => count($prepared['files']),
                'processed' => 0,
                'skipped' => 0,
                'errors' => $prepared['errors'],
            ];
        }

        $result = $this->processFiles($path, $prepared['files'], false);
        $result['errors'] = array_merge($result['errors'], $prepared['incompatible']);

        return $result;
    }

    private function prepareFiles(string $path, bool $latestPerDate): array
    {
        if (! is_dir($path) || ! is_readable($path)) {
            return [
                'files' => [],
                'total_files' => 0,
                'incompatible' => [],
                'errors' => ['Import path not found or is not readable: ' . $path],
            ];
        }

        $pattern = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'RVTools_ExportvInfo2csv_*.csv';
        $files = glob($pattern) ?: [];
        sort($files, SORT_STRING);
        $errors = [];

        if ($latestPerDate) {
            $latestFiles = [];
            foreach ($files as $filePath) {
                try {
                    $referenceDate = $this->fileInspector->referenceDate(basename($filePath));
                    $latestFiles[$referenceDate] = $filePath;
                } catch (RuntimeException $exception) {
                    $errors[] = basename($filePath) . ': ' . $exception->getMessage();
                }
            }
            ksort($latestFiles, SORT_STRING);
            $files = array_values($latestFiles);
        }

        $compatibleFiles = [];
        $incompatible = [];
        foreach ($files as $filePath) {
            $headerError = $this->fileInspector->headerError($filePath);
            if ($headerError !== null) {
                $incompatible[] = basename($filePath) . ': ' . $headerError;
                continue;
            }
            $compatibleFiles[] = $filePath;
        }

        return [
            'files' => $compatibleFiles,
            'total_files' => count(glob($pattern) ?: []),
            'incompatible' => $incompatible,
            'errors' => $errors,
        ];
    }

    private function processFiles(
        string $path,
        array $files,
        bool $force,
        ?callable $progress = null,
    ): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $importedMap = $this->fetchImportedMap();

        $processed = 0;
        $skipped = 0;
        $errors = [];

        $totalFiles = count($files);
        foreach ($files as $fileIndex => $filePath) {
            if (!is_file($filePath)) {
                continue;
            }

            $filename = basename($filePath);

            try {
                $referenceDate = $this->fileInspector->referenceDate($filename);
                $alreadyImported = isset($importedMap[$filename]);
                $hasInventory = $alreadyImported ? $this->hasInventoryForDate($referenceDate) : false;
                $sourceSha256 = hash_file('sha256', $filePath);
                if ($sourceSha256 === false) {
                    throw new RuntimeException('Unable to calculate CSV SHA-256.');
                }

                if ($force && $this->hasCompleteSnapshotForDate(
                    $referenceDate,
                    $filename,
                    $sourceSha256,
                )) {
                    $skipped++;
                    if ($progress !== null) {
                        $progress($fileIndex + 1, $totalFiles, $filename, 'skipped');
                    }
                    continue;
                }

                if (! $force
                    && $alreadyImported
                    && $hasInventory
                    && $this->hasCompleteSnapshotForDate(
                        $referenceDate,
                        $filename,
                        $sourceSha256,
                    )) {
                    $skipped++;
                    if ($progress !== null) {
                        $progress($fileIndex + 1, $totalFiles, $filename, 'skipped');
                    }
                    continue;
                }

                $sizeBefore = filesize($filePath);
                $scan = $this->scanFile($filePath, $sourceSha256);
                clearstatcache(true, $filePath);
                $sizeAfter = filesize($filePath);
                $sha256After = hash_file('sha256', $filePath);
                if ($sizeBefore === false
                    || $sizeAfter === false
                    || $sizeBefore !== $sizeAfter
                    || $sha256After === false
                    || ! hash_equals($sourceSha256, $sha256After)) {
                    throw new RuntimeException('CSV changed while it was being read.');
                }

                $inventory = $scan['inventory'];
                if ($inventory === []) {
                    throw new RuntimeException('CSV does not contain inventory rows.');
                }
                $this->registerOperatingSystems($scan['detected_operating_systems']);

                $inventory = $this->osFallbackResolver->apply(
                    $inventory,
                    $this->loadLatestKnownOsMap($referenceDate, array_keys($inventory)),
                    $this->ignoredNormalizedOperatingSystems,
                );
                $summary = $this->osFallbackResolver->summarize($inventory);

                $previousDate = $this->findPreviousDate($referenceDate);
                $previousInventory = $previousDate ? $this->loadInventoryMap($previousDate) : [];

                $newOsFlags = [];
                if ($previousInventory !== []) {
                    foreach ($inventory as $vm => $snapshot) {
                        if (! $snapshot['included_in_reports'] || isset($previousInventory[$vm])) {
                            continue;
                        }
                        $newOsFlags[$snapshot['os_name']] = true;
                    }
                }

                $this->db->transBegin();

                $this->db->table('rvtools_os_summary')
                    ->where('reference_date', $referenceDate)
                    ->delete();

                $this->db->table('rvtools_vm_inventory')
                    ->where('reference_date', $referenceDate)
                    ->delete();

                if ($summary !== []) {
                    $rows = [];
                    foreach ($summary as $osName => $count) {
                        $rows[] = [
                            'reference_date' => $referenceDate,
                            'os_name' => $osName,
                            'vm_count' => $count,
                            'has_new' => isset($newOsFlags[$osName]),
                        ];
                    }
                    $this->summaryModel->insertBatch($rows);
                }

                if ($inventory !== []) {
                    $rows = [];
                    foreach ($inventory as $vm => $snapshot) {
                        $rows[] = $snapshot + [
                            'reference_date' => $referenceDate,
                            'vm' => $vm,
                        ];
                        if (count($rows) >= 1000) {
                            $this->inventoryModel->insertBatch($rows);
                            $rows = [];
                        }
                    }
                    if ($rows !== []) {
                        $this->inventoryModel->insertBatch($rows);
                    }
                }

                if (!$alreadyImported) {
                    $this->importLogModel->insert([
                        'filename' => $filename,
                        'reference_date' => $referenceDate,
                    ]);
                }

                if ($this->db->transStatus() === false) {
                    throw new RuntimeException('Database transaction failed.');
                }

                $this->db->transCommit();
                $processed++;
                if ($progress !== null) {
                    $progress($fileIndex + 1, $totalFiles, $filename, 'processed');
                }
            } catch (\Throwable $exception) {
                $this->db->transRollback();
                $errors[] = $filename . ': ' . $exception->getMessage();
                if ($progress !== null) {
                    $progress($fileIndex + 1, $totalFiles, $filename, 'error');
                }
            }
        }

        return [
            'import_path' => $path,
            'selected' => count($files),
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function fetchImportedMap(): array
    {
        $rows = $this->importLogModel->select('filename')->findAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['filename']] = true;
        }
        return $map;
    }

    private function findPreviousDate(string $referenceDate): ?string
    {
        $row = $this->summaryModel->select('reference_date')
            ->where('reference_date <', $referenceDate)
            ->orderBy('reference_date', 'DESC')
            ->first();

        $date = $row['reference_date'] ?? null;
        return $date ?: null;
    }

    private function loadInventoryMap(string $referenceDate): array
    {
        $rows = $this->inventoryModel->select('vm, os_name')
            ->where('reference_date', $referenceDate)
            ->where('included_in_reports', true)
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $vm = $row['vm'] ?? '';
            if ($vm === '') {
                continue;
            }
            $map[$vm] = $row['os_name'] ?? '';
        }

        return $map;
    }

    private function loadLatestKnownOsMap(string $referenceDate, array $vms): array
    {
        if ($vms === []) {
            return [];
        }

        $rows = $this->inventoryModel
            ->select('DISTINCT ON (vm) vm, os_name', false)
            ->where('reference_date <', $referenceDate)
            ->where('included_in_reports', true)
            ->where('os_name_raw !=', '')
            ->whereIn('vm', $vms)
            ->orderBy('vm', 'ASC')
            ->orderBy('reference_date', 'DESC')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $vm = trim((string) ($row['vm'] ?? ''));
            $osName = trim((string) ($row['os_name'] ?? ''));
            if ($vm !== '' && $osName !== '') {
                $map[$vm] = $osName;
            }
        }

        return $map;
    }

    private function hasInventoryForDate(string $referenceDate): bool
    {
        return $this->inventoryModel
            ->where('reference_date', $referenceDate)
            ->countAllResults() > 0;
    }

    private function hasCompleteSnapshotForDate(
        string $referenceDate,
        string $filename,
        string $sourceSha256,
    ): bool
    {
        $matchingRows = $this->inventoryModel
            ->where('reference_date', $referenceDate)
            ->where('source_filename', $filename)
            ->where('source_sha256', $sourceSha256)
            ->countAllResults();

        if ($matchingRows === 0) {
            return false;
        }

        return ! $this->hasPendingOsFallback($referenceDate, $filename, $sourceSha256);
    }

    private function hasPendingOsFallback(
        string $referenceDate,
        string $filename,
        string $sourceSha256,
    ): bool {
        $builder = $this->db->table('rvtools_vm_inventory current_inventory');
        $builder->select('current_inventory.vm');
        $builder->join(
            'rvtools_vm_inventory previous_inventory',
            "previous_inventory.vm = current_inventory.vm
             AND previous_inventory.reference_date < current_inventory.reference_date
             AND previous_inventory.included_in_reports = TRUE
             AND previous_inventory.os_name_raw <> ''",
            'inner',
            false,
        );
        $builder->where('current_inventory.reference_date', $referenceDate);
        $builder->where('current_inventory.source_filename', $filename);
        $builder->where('current_inventory.source_sha256', $sourceSha256);
        $builder->where('current_inventory.power_state', 'poweredOn');
        $builder->where('current_inventory.os_name_raw', '');
        $builder->where('current_inventory.included_in_reports', false);
        $builder->limit(1);

        return $builder->get()->getFirstRow() !== null;
    }

    private function scanFile(string $filePath, string $sourceSha256): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV.');
        }

        $header = fgetcsv($handle, 0, ';', '"', '');
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV header not found.');
        }

        $header = array_map([$this, 'normalizeHeaderValue'], $header);
        $vmIndex = array_search('VM', $header, true);
        $powerIndex = array_search('Powerstate', $header, true);
        $osIndex = array_search('OS according to the VMware Tools', $header, true);
        $dnsIndex = array_search('DNS Name', $header, true);
        $ipIndex = array_search('Primary IP Address', $header, true);
        if ($ipIndex === false) {
            $ipIndex = array_search('IP Address', $header, true);
        }
        $creationIndex = array_search('Creation date', $header, true);
        $annotationIndex = array_search('Annotation', $header, true);

        if ($vmIndex === false || $powerIndex === false || $osIndex === false || $dnsIndex === false) {
            fclose($handle);
            throw new RuntimeException('Required columns not found.');
        }

        $inventory = [];
        $detectedOperatingSystems = [];
        $filename = basename($filePath);
        $importedAt = date('Y-m-d H:i:s');

        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $vm = $this->sanitizeUtf8(trim((string) ($row[$vmIndex] ?? '')));
            if ($vm === '') {
                continue;
            }

            $power = $this->sanitizeUtf8(trim((string) ($row[$powerIndex] ?? '')));
            $os = $this->sanitizeUtf8(trim((string) ($row[$osIndex] ?? '')));
            $normalized = $os !== '' && strcasecmp($os, 'nan') !== 0
                ? $this->normalizeOs($os)
                : '';
            if ($normalized !== '') {
                $detectedOperatingSystems[$os] = true;
            }
            $included = $this->operatingSystemPolicy->included(
                $power,
                $os,
                $normalized,
                $this->operatingSystemPolicies,
            );

            $rawData = [];
            foreach ($header as $columnIndex => $columnName) {
                if ($columnName === '') {
                    continue;
                }
                $rawData[$columnName] = $this->sanitizeUtf8((string) ($row[$columnIndex] ?? ''));
            }

            if (! isset($inventory[$vm])) {
                $inventory[$vm] = [
                    'os_name' => $normalized,
                    'power_state' => $power,
                    'dns_name' => $this->sanitizeUtf8(trim((string) ($row[$dnsIndex] ?? ''))),
                    'primary_ip' => $ipIndex !== false
                        ? $this->sanitizeUtf8(trim((string) ($row[$ipIndex] ?? '')))
                        : '',
                    'os_name_raw' => $os,
                    'creation_date_raw' => $creationIndex !== false
                        ? $this->sanitizeUtf8(trim((string) ($row[$creationIndex] ?? '')))
                        : '',
                    'annotation' => $annotationIndex !== false
                        ? $this->sanitizeUtf8(trim((string) ($row[$annotationIndex] ?? '')))
                        : '',
                    'source_filename' => $filename,
                    'source_sha256' => $sourceSha256,
                    'raw_data' => json_encode(
                        $rawData,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
                    ),
                    'included_in_reports' => $included,
                    'imported_at' => $importedAt,
                ];

            }
        }

        fclose($handle);

        return [
            'inventory' => $inventory,
            'detected_operating_systems' => array_keys($detectedOperatingSystems),
        ];
    }

    private function loadOperatingSystemPolicies(): array
    {
        if (! $this->db->tableExists('operating_system_policies')) {
            return [];
        }

        $policies = [];
        foreach ($this->db->table('operating_system_policies')->select('os_name, normalized_name, is_ignored')->get()->getResultArray() as $row) {
            $name = trim((string) ($row['os_name'] ?? ''));
            if ($name !== '') {
                $ignored = in_array($row['is_ignored'] ?? false, [true, 1, '1', 't', 'true'], true);
                $policies[$name] = $ignored;
                $normalized = trim((string) ($row['normalized_name'] ?? ''));
                if ($ignored && $normalized !== '') {
                    $this->ignoredNormalizedOperatingSystems[$normalized] = true;
                }
            }
        }

        return $policies;
    }

    private function registerOperatingSystems(array $operatingSystems): void
    {
        if (! $this->db->tableExists('operating_system_policies')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($operatingSystems as $operatingSystem) {
            $name = trim((string) $operatingSystem);
            if ($name === '' || array_key_exists($name, $this->operatingSystemPolicies)) {
                continue;
            }
            $ignored = $this->operatingSystemPolicy->ignoredByDefault($name);
            $this->operatingSystemPolicies[$name] = $ignored;
            $normalized = $this->normalizeOs($name);
            if ($ignored) {
                $this->ignoredNormalizedOperatingSystems[$normalized] = true;
            }
            $rows[] = [
                'os_name' => $name,
                'normalized_name' => $normalized,
                'is_ignored' => $ignored,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            $this->db->table('operating_system_policies')->ignore(true)->insertBatch($rows);
        }
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if ($converted !== false) {
                return $converted;
            }
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);

        return $converted !== false ? $converted : '';
    }

    private function normalizeHeaderValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }

    private function normalizeOs(string $os): string
    {
        if ($this->startsWith($os, 'CentOS')) {
            return 'CentOS';
        }

        if (
            $this->startsWith($os, 'Other')
            || $this->startsWith($os, 'SUSE ')
            || $this->startsWith($os, 'FreeB')
        ) {
            return 'Other';
        }

        $clean = str_replace(' (64-bit)', '', $os);
        $clean = trim($clean);

        if (strlen($clean) > $this->osMaxLength) {
            $clean = substr($clean, 0, $this->osMaxLength);
        }

        return $clean;
    }

    private function startsWith(string $value, string $prefix): bool
    {
        return strncmp($value, $prefix, strlen($prefix)) === 0;
    }

    private function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($this->startsWith($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveImportPath(string $configuredPath): string
    {
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
}
