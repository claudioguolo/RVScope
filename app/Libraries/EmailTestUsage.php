<?php

namespace App\Libraries;

use InvalidArgumentException;
use RuntimeException;

final class EmailTestUsage
{
    public const DEFAULT_INTERVAL_SECONDS = 60;
    public const MAX_INTERVAL_SECONDS = 3600;

    public function __construct(private readonly ?string $path = null)
    {
    }

    public static function validateInterval(mixed $input): int
    {
        $interval = is_int($input) || is_string($input)
            ? filter_var($input, FILTER_VALIDATE_INT, ['options' => [
                'min_range' => 0, 'max_range' => self::MAX_INTERVAL_SECONDS,
            ]])
            : false;
        if ($interval === false) {
            throw new InvalidArgumentException('Informe um intervalo inteiro entre 0 e 3600 segundos.');
        }
        return $interval;
    }

    public function record(int $userId, int $intervalSeconds = self::DEFAULT_INTERVAL_SECONDS): void
    {
        self::validateInterval($intervalSeconds);
        // O próprio log serializa as tentativas e limita o uso entre sessões do mesmo usuário.
        $file = fopen($this->path ?? WRITEPATH . 'logs/email-test-usage.log', 'a+');
        if ($file === false) {
            throw new RuntimeException('Não foi possível registrar o uso. Tente novamente mais tarde.');
        }
        try {
            if (! flock($file, LOCK_EX)) {
                throw new RuntimeException('Não foi possível registrar o uso.');
            }
            rewind($file);
            $now = time();
            while ($intervalSeconds > 0 && ($line = fgets($file)) !== false) {
                $entry = json_decode($line, true);
                if (is_array($entry) && ($entry['user_id'] ?? null) === $userId
                    && $now - (int) ($entry['timestamp'] ?? 0) < $intervalSeconds) {
                    $unit = $intervalSeconds === 1 ? 'segundo' : 'segundos';
                    throw new RuntimeException('Aguarde ' . $intervalSeconds . ' ' . $unit . ' entre os testes.');
                }
            }
            $line = json_encode(['user_id' => $userId, 'timestamp' => $now, 'date' => gmdate('c')]) . "\n";
            if (fwrite($file, $line) !== strlen($line) || ! fflush($file)) {
                throw new RuntimeException('Não foi possível registrar o uso.');
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
