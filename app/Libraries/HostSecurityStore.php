<?php

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Um snapshot por hostname/dia, em UTC, no volume persistente writable. */
final class HostSecurityStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? WRITEPATH . 'host-security';
    }

    public function dates(): array
    {
        $dates = [];
        if (is_dir($this->directory) && ! is_readable($this->directory)) {
            throw new RuntimeException('Histórico indisponível.');
        }
        foreach (glob($this->directory . '/*.json') ?: [] as $path) {
            $date = basename($path, '.json');
            if ($this->validDate($date)) {
                $dates[] = $date;
            }
        }
        rsort($dates, SORT_STRING);
        return $dates;
    }

    public function read(string $date): array
    {
        if (! $this->validDate($date)) {
            throw new RuntimeException('Data inválida.');
        }
        $path = $this->directory . '/' . $date . '.json';
        if (! file_exists($path)) {
            return [];
        }
        if (is_link($path) || ! is_readable($path) || filesize($path) > 10 * 1024 * 1024) {
            throw new RuntimeException('Histórico indisponível.');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Histórico indisponível.');
        }
        $records = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($records) || ! array_is_list($records)) {
            throw new RuntimeException('Histórico inválido.');
        }
        foreach ($records as $record) {
            if (! is_array($record) || ! isset($record['collected_at']) || ! is_string($record['collected_at'])
                || ! preg_match('/\A' . preg_quote($date, '/') . ' [0-2][0-9]:[0-5][0-9]:[0-5][0-9]\z/', $record['collected_at'])) {
                throw new RuntimeException('Histórico inválido.');
            }
            (new HostSecurityCollection())->validate($record);
        }
        return $records;
    }

    public function save(array $input, ?DateTimeImmutable $now = null): array
    {
        $record = (new HostSecurityCollection())->validate($input);
        $now = ($now ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));
        $date = $now->format('Y-m-d');
        $record['collected_at'] = $now->format('Y-m-d H:i:s');
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Não foi possível criar o histórico.');
        }
        $lock = fopen($this->directory . '/' . $date . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Não foi possível bloquear o histórico.');
        }
        $temporary = null;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear o histórico.');
            }
            $records = [];
            foreach ([...$this->read($date), $record] as $candidate) {
                $hostname = strtolower(rtrim(trim($candidate['hostname']), '.'));
                if (! isset($records[$hostname]) || $records[$hostname]['collected_at'] <= $candidate['collected_at']) {
                    $records[$hostname] = $candidate;
                }
            }
            ksort($records);
            $output = json_encode(array_values($records), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (strlen($output) > 10 * 1024 * 1024) {
                throw new RuntimeException('Histórico diário excede 10 MiB.');
            }
            $temporary = tempnam($this->directory, '.security-');
            if ($temporary === false || file_put_contents($temporary, $output) !== strlen($output)
                || ! chmod($temporary, 0640) || ! rename($temporary, $this->directory . '/' . $date . '.json')) {
                throw new RuntimeException('Não foi possível salvar o histórico.');
            }
            $temporary = null;
            return $record;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
