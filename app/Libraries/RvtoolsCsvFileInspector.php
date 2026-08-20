<?php

namespace App\Libraries;

use RuntimeException;

final class RvtoolsCsvFileInspector
{
    public function headerError(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return 'Unable to open CSV.';
        }

        $header = fgetcsv($handle, 0, ';', '"', '');
        fclose($handle);
        if ($header === false) {
            return 'CSV header not found.';
        }

        $header = array_map([$this, 'normalizeHeader'], $header);
        foreach (['VM', 'Powerstate', 'DNS Name', 'OS according to the VMware Tools'] as $requiredColumn) {
            if (! in_array($requiredColumn, $header, true)) {
                return 'Required column not found: ' . $requiredColumn;
            }
        }

        return null;
    }

    public function referenceDate(string $filename): string
    {
        if (preg_match('/^RVTools_ExportvInfo2csv_(\d{4}-\d{2}-\d{2})_\d{2}\.\d{2}\.\d{2}\.csv$/', $filename, $matches) !== 1) {
            throw new RuntimeException('Filename does not contain a valid RVTools timestamp.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $matches[1]) {
            throw new RuntimeException('Filename does not contain a valid date.');
        }

        return $matches[1];
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', trim($value));
    }
}
