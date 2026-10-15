<?php

namespace App\Libraries;

use RuntimeException;

final class EmailTestUsage
{
    public function __construct(private readonly ?string $path = null)
    {
    }

    public function record(int $userId): void
    {
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
            while (($line = fgets($file)) !== false) {
                $entry = json_decode($line, true);
                if (is_array($entry) && ($entry['user_id'] ?? null) === $userId
                    && $now - (int) ($entry['timestamp'] ?? 0) < 60) {
                    throw new RuntimeException('Aguarde 60 segundos entre os testes.');
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
