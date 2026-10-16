<?php

namespace App\Libraries;

use InvalidArgumentException;

final class SmtpTransportConfiguration
{
    public static function fromArray(array $input, int $defaultPort = 587, string $defaultCrypto = 'tls'): array
    {
        $portInput = $input['port'] ?? $defaultPort;
        $port = is_string($portInput) || is_int($portInput)
            ? filter_var($portInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])
            : false;
        if ($port === false) {
            throw new InvalidArgumentException('Informe uma porta SMTP válida, entre 1 e 65535.');
        }

        $cryptoInput = $input['crypto'] ?? $defaultCrypto;
        if (! is_string($cryptoInput)) {
            throw new InvalidArgumentException('Selecione uma segurança SMTP válida.');
        }
        $crypto = strtolower(trim($cryptoInput));
        if (! in_array($crypto, ['tls', 'ssl', 'none', ''], true)) {
            throw new InvalidArgumentException('Selecione uma segurança SMTP válida.');
        }

        return ['port' => $port, 'crypto' => $crypto === 'none' ? '' : $crypto];
    }
}
