<?php

namespace App\Libraries;

use InvalidArgumentException;

final class EmailTestRecipients
{
    public static function parse(string $input): array
    {
        if (strlen($input) > 6000) {
            throw new InvalidArgumentException('A lista de destinatários é muito longa.');
        }
        $addresses = preg_split('/[\s,;]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($addresses as $address) {
            if (strlen($address) > 254 || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Informe somente endereços de e-mail válidos, separados por vírgula, ponto e vírgula ou linha.');
            }
            $result[strtolower($address)] = strtolower($address);
        }
        if (count($result) > 20) {
            throw new InvalidArgumentException('São permitidos até 20 destinatários por teste.');
        }
        return array_values($result);
    }
}
