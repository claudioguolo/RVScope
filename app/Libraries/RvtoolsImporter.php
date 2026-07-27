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

    public function __construct(?BaseConnection $db = null, ?RvtoolsConfig $config = null)
    {
        $this->db = $db ?? db_connect();
        $this->summaryModel = new RvtoolsOsSummaryModel($this->db);
        $this->inventoryModel = new RvtoolsVmInventoryModel($this->db);
        $this->importLogModel = new RvtoolsImportLogModel($this->db);

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
                    $referenceDate = $this->extractReferenceDate(basename($filePath));
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
            $headerError = $this->validateFileHeader($filePath);
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

    private function validateFileHeader(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return 'Unable to open CSV.';
        }

        $header = fgetcsv($handle, 0, ';');
        fclose($handle);
        if ($header === false) {
            return 'CSV header not found.';
        }

        $header = array_map([$this, 'normalizeHeaderValue'], $header);
        foreach (['VM', 'Powerstate', 'DNS Name', 'OS according to the VMware Tools'] as $requiredColumn) {
            if (! in_array($requiredColumn, $header, true)) {
                return 'Required column not found: ' . $requiredColumn;
            }
        }

        return null;
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
                $referenceDate = $this->extractReferenceDate($filename);
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

                $summary = $scan['summary'];
                $inventory = $scan['inventory'];
                if ($inventory === []) {
                    throw new RuntimeException('CSV does not contain inventory rows.');
                }

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
        return $this->inventoryModel
            ->where('reference_date', $referenceDate)
            ->where('source_filename', $filename)
            ->where('source_sha256', $sourceSha256)
            ->countAllResults() > 0;
    }

    private function scanFile(string $filePath, string $sourceSha256): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV.');
        }

        $header = fgetcsv($handle, 0, ';');
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

        $counts = [];
        $inventory = [];
        $filename = basename($filePath);
        $importedAt = date('Y-m-d H:i:s');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $vm = $this->sanitizeUtf8(trim((string) ($row[$vmIndex] ?? '')));
            if ($vm === '') {
                continue;
            }

            $power = $this->sanitizeUtf8(trim((string) ($row[$powerIndex] ?? '')));
            $os = $this->sanitizeUtf8(trim((string) ($row[$osIndex] ?? '')));
            $normalized = $os !== '' && strcasecmp($os, 'nan') !== 0
                ? $this->normalizeOs($os)
                : '';
            $included = $power === 'poweredOn'
                && $normalized !== ''
                && ! $this->startsWithAny($os, ['Microsoft', 'VMware', 'Forti']);

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

                if ($included) {
                    $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
                }
            }
        }

        fclose($handle);

        return [
            'summary' => $counts,
            'inventory' => $inventory,
        ];
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

    private function extractReferenceDate(string $filename): string
    {
        if (strlen($filename) < 23) {
            throw new RuntimeException('Filename too short to extract date.');
        }

        $datePart = substr($filename, -23, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart)) {
            throw new RuntimeException('Filename does not contain a valid date.');
        }

        return $datePart;
    }

    private function summarizeFile(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV.');
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV header not found.');
        }

        $header = array_map([$this, 'normalizeHeaderValue'], $header);
        $powerIndex = array_search('Powerstate', $header, true);
        $osIndex = array_search('OS according to the VMware Tools', $header, true);

        if ($powerIndex === false || $osIndex === false) {
            fclose($handle);
            throw new RuntimeException('Required columns not found.');
        }

        $counts = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $power = trim((string) ($row[$powerIndex] ?? ''));
            if ($power !== 'poweredOn') {
                continue;
            }

            $os = trim((string) ($row[$osIndex] ?? ''));
            if ($os === '' || strcasecmp($os, 'nan') === 0) {
                continue;
            }

            if ($this->startsWithAny($os, ['Microsoft', 'VMware', 'Forti'])) {
                continue;
            }

            $normalized = $this->normalizeOs($os);
            if ($normalized === '') {
                continue;
            }

            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        fclose($handle);
        return $counts;
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
