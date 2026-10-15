<?php

namespace App\Libraries;

use InvalidArgumentException;

final class EmailTestRequest
{
    public const DOMAIN = '@envio.tjmt.jus.br';

    public static function account(string $localPart): string
    {
        $localPart = trim($localPart);
        $address = $localPart . self::DOMAIN;
        if ($localPart === '' || str_contains($localPart, '@') || strlen($address) > 254
            || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe somente o nome da conta, sem @ ou domínio.');
        }
        return $address;
    }

    public static function resolve(array $input, string $defaults): array
    {
        $mode = (string) ($input['mode'] ?? '');
        if (! in_array($mode, ['quick', 'custom'], true)) {
            throw new InvalidArgumentException('Selecione Teste Rápido ou Teste Personalizado.');
        }
        $recipients = EmailTestRecipients::parse($defaults);
        if ($recipients === []) {
            throw new InvalidArgumentException('Solicite ao administrador o cadastro dos destinatários padrão.');
        }
        $account = null;
        if ($mode === 'custom') {
            $username = self::account((string) ($input['username'] ?? ''));
            $password = (string) ($input['password'] ?? '');
            if ($password === '' || strlen($password) > 4096) {
                throw new InvalidArgumentException('Informe a senha da conta alternativa.');
            }
            $account = ['username' => $username, 'password' => $password];
            $customRecipients = trim((string) ($input['custom_recipients'] ?? ''));
            if ($customRecipients !== '') {
                $recipients = EmailTestRecipients::parse($customRecipients);
            }
        }
        return ['recipients' => $recipients, 'account' => $account];
    }
}
