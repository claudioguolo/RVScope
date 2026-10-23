<?php

namespace App\Libraries;

use InvalidArgumentException;
use RuntimeException;

/** Histórico em volume persistente, fora do diretório público, com atualização atômica. */
final class ErrataFileStore
{
    private readonly string $directory;
    private readonly ErrataDataParser $parser;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? WRITEPATH . 'erratas';
        $this->parser = new ErrataDataParser();
    }

    public function read(string $kind): array
    {
        $path = $this->path($kind);
        if (! file_exists($path)) {
            return ['records' => [], 'invalid_lines' => [], 'exists' => false];
        }
        if (! is_file($path) || ! is_readable($path) || is_link($path)) {
            throw new RuntimeException('O histórico de erratas não pode ser lido. Solicite ao administrador que confira as permissões do volume writable.');
        }
        $content = file_get_contents($path, false, null, 0, ErrataDataParser::MAX_BYTES + 1);
        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o histórico de erratas.');
        }
        return $this->parse($kind, $content) + ['exists' => true];
    }

    public function merge(string $kind, string $content): array
    {
        $incoming = $this->parse($kind, $content);
        if ($incoming['invalid_lines'] !== []) {
            throw new InvalidArgumentException('Arquivo recusado: há registros inválidos nas linhas ' . implode(', ', array_slice($incoming['invalid_lines'], 0, 10)) . '. O histórico anterior foi preservado.');
        }
        if ($incoming['records'] === []) {
            throw new InvalidArgumentException('O arquivo não contém coletas válidas. O histórico anterior foi preservado.');
        }
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Não foi possível criar o diretório de erratas no volume writable.');
        }
        $path = $this->path($kind);
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Não foi possível bloquear o histórico de erratas para atualização.');
        }
        $temporary = null;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear o histórico de erratas para atualização.');
            }
            $previous = $this->read($kind);
            if ($previous['invalid_lines'] !== []) {
                throw new RuntimeException('O histórico existente contém linhas inválidas. O administrador deve conferir o arquivo antes de importar novas coletas.');
            }
            $records = [];
            foreach (array_merge($previous['records'], $incoming['records']) as $record) {
                $records[$this->recordKey($kind, $record)] = $record;
            }
            ksort($records, SORT_STRING);
            $output = $this->serialize($kind, $records);
            if (strlen($output) > ErrataDataParser::MAX_BYTES || count($records) > 50000) {
                throw new InvalidArgumentException('O histórico excederia 10 MiB ou 50 mil registros. O arquivo anterior foi preservado.');
            }
            $temporary = tempnam($this->directory, '.erratas-');
            if ($temporary === false || file_put_contents($temporary, $output) !== strlen($output)
                || ! chmod($temporary, 0640) || ! rename($temporary, $path)) {
                throw new RuntimeException('Não foi possível salvar o histórico de erratas. Confira o espaço e as permissões do volume writable.');
            }
            $temporary = null;
            return ['received' => count($incoming['records']), 'stored' => count($records)];
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function path(string $kind): string
    {
        return rtrim($this->directory, '/') . '/' . match ($kind) {
            'daily' => 'erratas.txt',
            'hosts' => 'erratas_hosts.csv',
            default => throw new InvalidArgumentException('Selecione uma fonte de erratas válida.'),
        };
    }

    private function parse(string $kind, string $content): array
    {
        $this->path($kind);
        return $kind === 'daily' ? $this->parser->daily($content) : $this->parser->hosts($content);
    }

    private function recordKey(string $kind, array $record): string
    {
        $key = $record['collected_at'];
        if ($kind === 'hosts') {
            $key .= '|' . ErrataDataParser::hostIdentity($record);
            $key .= $record['format'] === 'items' ? '|' . strtoupper($record['items'][0]['label'] ?? '') : '|counts';
        }
        return $key;
    }

    private function serialize(string $kind, array $records): string
    {
        if ($kind === 'daily') {
            return implode('', array_map(static fn ($record) => \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $record['collected_at'])->format('d-m-YH:i:s')
                . ' - ' . implode(' ; ', array_column($record['items'], 'label')) . " ;\n", array_values($records)));
        }
        $stream = fopen('php://temp', 'w+');
        try {
            fputcsv($stream, ['collected_at', 'host', 'hostname', 'ip', 'security', 'bugfix', 'enhancement', 'unspecified', 'total', 'erratum_id'], ';', '"', '');
            foreach ($records as $record) {
                $individual = $record['format'] === 'items';
                fputcsv($stream, [$record['collected_at'], $record['host'] ?? '', $record['hostname'] ?? '', $record['ip'] ?? '',
                    ...($individual ? ['', '', '', '', ''] : [...array_values($record['counts']), $record['total']]),
                    $individual ? $record['items'][0]['label'] : ''], ';', '"', '');
            }
            rewind($stream);
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }
}
