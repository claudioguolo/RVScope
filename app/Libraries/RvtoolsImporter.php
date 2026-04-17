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
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (!is_dir($this->importPath)) {
            return [
                'import_path' => $this->importPath,
                'processed' => 0,
                'skipped' => 0,
                'errors' => ['Import path not found.'],
            ];
        }

        $pattern = rtrim($this->importPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'RVTools_ExportvInfo2csv_*.csv';
        $files = glob($pattern) ?: [];
        sort($files, SORT_STRING);

        $importedMap = $this->fetchImportedMap();

        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }

            $filename = basename($filePath);

            try {
                $referenceDate = $this->extractReferenceDate($filename);
                $alreadyImported = isset($importedMap[$filename]);
                $hasInventory = $alreadyImported ? $this->hasInventoryForDate($referenceDate) : false;

                if ($alreadyImported && $hasInventory) {
                    $skipped++;
                    continue;
                }

                $scan = $this->scanFile($filePath);
                $summary = $scan['summary'];
                $inventory = $scan['inventory'];

                $previousDate = $this->findPreviousDate($referenceDate);
                $previousInventory = $previousDate ? $this->loadInventoryMap($previousDate) : [];

                $newOsFlags = [];
                if ($previousInventory !== []) {
                    foreach ($inventory as $vm => $osName) {
                        if (!isset($previousInventory[$vm])) {
                            $newOsFlags[$osName] = true;
                        }
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
                    foreach ($inventory as $vm => $osName) {
                        $rows[] = [
                            'reference_date' => $referenceDate,
                            'vm' => $vm,
                            'os_name' => $osName,
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
            } catch (RuntimeException $exception) {
                $this->db->transRollback();
                $errors[] = $filename . ': ' . $exception->getMessage();
            }
        }

        return [
            'import_path' => $this->importPath,
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

    private function scanFile(string $filePath): array
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

        if ($vmIndex === false || $powerIndex === false || $osIndex === false) {
            fclose($handle);
            throw new RuntimeException('Required columns not found.');
        }

        $counts = [];
        $inventory = [];

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

            $vm = trim((string) ($row[$vmIndex] ?? ''));
            if ($vm === '') {
                continue;
            }

            if (!isset($inventory[$vm])) {
                $inventory[$vm] = $normalized;
                $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
            }
        }

        fclose($handle);

        return [
            'summary' => $counts,
            'inventory' => $inventory,
        ];
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
