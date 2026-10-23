<?php

namespace App\Libraries;

use DateTimeImmutable;
use InvalidArgumentException;

/** Normaliza o TXT legado e uma exportação de coletas por host, sem acessar o Satellite. */
final class ErrataDataParser
{
    public const MAX_BYTES = 10485760;
    private const TYPES = ['security', 'bugfix', 'enhancement', 'unspecified'];

    public function daily(string $content): array
    {
        $this->validateContent($content);
        $records = $invalid = [];
        foreach (preg_split('/\r\n|\n|\r/', ltrim($content, "\xEF\xBB\xBF")) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            try {
                $parts = explode(' - ', $line, 2);
                if (count($parts) !== 2) {
                    throw new InvalidArgumentException();
                }
                $timestamp = $this->timestamp(trim($parts[0]));
                $items = array_values(array_filter(array_map('trim', explode(';', $parts[1])), static fn ($item) => $item !== ''));
                $records[] = $this->record($timestamp, $items);
            } catch (InvalidArgumentException $exception) {
                $invalid[] = $index + 1;
            }
        }
        return ['records' => $records, 'invalid_lines' => $invalid];
    }

    public function hosts(string $content): array
    {
        $this->validateContent($content);
        if (trim($content) === '') {
            return ['records' => [], 'invalid_lines' => []];
        }
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, ltrim($content, "\xEF\xBB\xBF"));
            rewind($stream);
            $firstLine = fgets($stream);
            $delimiter = count(str_getcsv($firstLine, ';', '"', '')) > count(str_getcsv($firstLine, ',', '"', '')) ? ';' : ',';
            rewind($stream);
            $header = fgetcsv($stream, 0, $delimiter, '"', '');
            $header = array_map(static fn ($field) => strtolower(trim((string) $field)), $header);
            if (count($header) !== count(array_unique($header)) || ! in_array('collected_at', $header, true)
                || array_intersect(['host', 'hostname', 'ip'], $header) === []
                || array_intersect([...self::TYPES, 'total', 'erratum_id'], $header) === []) {
                throw new InvalidArgumentException('Cabeçalho CSV inválido. Use collected_at, host/hostname/ip e contagens por tipo ou erratum_id.');
            }
            $records = $invalid = [];
            $line = 1;
            while (($fields = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                ++$line;
                if ($fields === [null]) {
                    continue;
                }
                try {
                    if (count($fields) !== count($header)) {
                        throw new InvalidArgumentException();
                    }
                    $row = array_map(static fn ($value) => trim((string) $value), array_combine($header, $fields));
                    $timestamp = $this->timestamp($row['collected_at']);
                    $identity = array_intersect_key($row, array_flip(['host', 'hostname', 'ip']));
                    if (implode('', $identity) === '' || max(array_map('mb_strlen', $identity)) > 254
                        || preg_match('/[\x00-\x1f\x7f]/', implode('', $identity))
                        || (($row['ip'] ?? '') !== '' && ! filter_var($row['ip'], FILTER_VALIDATE_IP))) {
                        throw new InvalidArgumentException();
                    }
                    $items = [];
                    if (($row['erratum_id'] ?? '') !== '') {
                        if (preg_match('/\ARH(?:SA|BA|EA)-[0-9]{4}:[0-9]+\z/i', $row['erratum_id']) !== 1) {
                            throw new InvalidArgumentException();
                        }
                        foreach ([...self::TYPES, 'total'] as $type) {
                            if (($row[$type] ?? '') !== '') {
                                throw new InvalidArgumentException();
                            }
                        }
                        $items[] = strtoupper($row['erratum_id']);
                    } else {
                        foreach ([...self::TYPES, 'total'] as $type) {
                            if (($row[$type] ?? '') !== '') {
                                $this->quantity($row[$type]);
                                $items[] = $type . ': ' . $row[$type];
                            }
                        }
                        if ($items === []) {
                            throw new InvalidArgumentException();
                        }
                    }
                    $records[] = $this->record($timestamp, $items) + $identity;
                } catch (InvalidArgumentException $exception) {
                    $invalid[] = $line;
                }
            }
            return ['records' => $records, 'invalid_lines' => $invalid];
        } finally {
            fclose($stream);
        }
    }

    public function combineItems(array $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            foreach ($record['items'] as $item) {
                $ids[strtoupper($item['label'])] = $item['label'];
            }
        }
        return $this->record($records[0]['collected_at'], array_values($ids));
    }

    public static function hostIdentity(array $record): string
    {
        $name = trim(($record['hostname'] ?? '') ?: ($record['host'] ?? ''));
        if ($name !== '') {
            return 'name:' . mb_strtolower(rtrim($name, '.'));
        }
        $ip = trim($record['ip'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? 'ip:' . bin2hex(inet_pton($ip)) : '';
    }

    private function record(string $timestamp, array $labels): array
    {
        if ($labels === []) {
            throw new InvalidArgumentException();
        }
        $counts = array_fill_keys(self::TYPES, 0);
        $items = [];
        $declaredTotal = null;
        $format = null;
        $seen = [];
        foreach ($labels as $label) {
            if (mb_strlen($label) > 2000 || count($labels) > 10000) {
                throw new InvalidArgumentException();
            }
            $isId = preg_match('/\ARH(?:SA|BA|EA)-[0-9]{4}:[0-9]+(?:\s.*)?\z/i', $label) === 1;
            $counted = ! $isId && preg_match('/\A(.+?)\s*[:=]\s*([0-9]+)\z/u', $label, $match) === 1;
            if (! $isId && ! $counted && preg_match('/\A([0-9]+)\s+(.+)\z/u', $label, $prefix) === 1) {
                $counted = true;
                $match = ['', $prefix[2], $prefix[1]];
            }
            if (! $isId && ! $counted && (preg_match('/\A[-+]?[0-9]+(?:[.,][0-9]+)?\s+/', $label)
                || preg_match('/\A(?:security|bugfix|enhancement|unspecified|total)\s*[:=]/i', $label))) {
                throw new InvalidArgumentException();
            }
            $currentFormat = $counted ? 'counts' : 'items';
            if ($format !== null && $format !== $currentFormat) {
                throw new InvalidArgumentException();
            }
            $format = $currentFormat;
            $name = $counted ? trim($match[1]) : $label;
            $quantity = $counted ? $this->quantity($match[2]) : 1;
            if ($counted && in_array(mb_strtolower($name), ['total', 'total de erratas'], true)) {
                if ($declaredTotal !== null) {
                    throw new InvalidArgumentException();
                }
                $declaredTotal = $quantity;
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                if ($counted) {
                    throw new InvalidArgumentException();
                }
                continue;
            }
            $seen[$key] = true;
            $type = $this->type($name);
            $counts[$type] += $quantity;
            $items[] = ['label' => $label, 'quantity' => $quantity, 'type' => $type];
        }
        if ($declaredTotal !== null && $items === []) {
            $counts['unspecified'] = $declaredTotal;
            $items[] = ['label' => 'Total: ' . $declaredTotal, 'quantity' => $declaredTotal, 'type' => 'unspecified'];
        }
        if ($declaredTotal !== null && $declaredTotal !== array_sum($counts)) {
            throw new InvalidArgumentException();
        }
        return ['collected_at' => $timestamp, 'date' => substr($timestamp, 0, 10),
            'counts' => $counts, 'total' => array_sum($counts), 'items' => $items, 'format' => $format ?? 'items'];
    }

    private function type(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), ['ç' => 'c', 'ã' => 'a', 'õ' => 'o', 'é' => 'e', 'ó' => 'o']);
        return match (true) {
            str_starts_with($value, 'rhsa-'), str_contains($value, 'security'), str_contains($value, 'seguranca') => 'security',
            str_starts_with($value, 'rhba-'), str_contains($value, 'bugfix'), str_contains($value, 'bug fix'), str_contains($value, 'correc') => 'bugfix',
            str_starts_with($value, 'rhea-'), str_contains($value, 'enhancement'), str_contains($value, 'melhoria') => 'enhancement',
            default => 'unspecified',
        };
    }

    private function quantity(string $value): int
    {
        if (! preg_match('/\A[0-9]{1,9}\z/', $value)) {
            throw new InvalidArgumentException();
        }
        return (int) $value;
    }

    private function timestamp(string $value): string
    {
        $value = str_replace('T', ' ', $value);
        foreach (['d-m-YH:i', 'd-m-YH:i:s', 'd-m-Y H:i', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        throw new InvalidArgumentException();
    }

    private function validateContent(string $content): void
    {
        if (strlen($content) > self::MAX_BYTES || max(substr_count($content, "\n"), substr_count($content, "\r")) > 50000
            || ! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw new InvalidArgumentException('Use um arquivo de texto UTF-8 de até 10 MiB e 50 mil registros.');
        }
    }
}
