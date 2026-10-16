<?php

namespace App\Libraries;

use InvalidArgumentException;

final class EmailTestRequest
{
    public const DEFAULT_DOMAIN = 'envio.tjmt.jus.br';

    public static function domain(): string
    {
        $configured = function_exists('env')
            ? env('emailTest.domain', self::DEFAULT_DOMAIN)
            : (getenv('emailTest.domain') !== false ? getenv('emailTest.domain') : self::DEFAULT_DOMAIN);
        if (! is_string($configured)) {
            throw new InvalidArgumentException('O domínio das contas de teste é inválido. Verifique emailTest.domain no ambiente.');
        }
        $domain = strtolower(trim($configured));
        if (str_starts_with($domain, '@')) {
            $domain = substr($domain, 1);
        }
        if ($domain === '' || strlen($domain) > 253
            || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || ! filter_var('rvscope@' . $domain, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('O domínio das contas de teste é inválido. Verifique emailTest.domain no ambiente.');
        }
        return $domain;
    }

    public static function matchesDomain(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            && str_ends_with(strtolower($address), '@' . self::domain());
    }

    public static function account(string $localPart): string
    {
        $localPart = trim($localPart);
        $address = $localPart . '@' . self::domain();
        if ($localPart === '' || strlen($localPart) > 64 || str_contains($localPart, '@') || strlen($address) > 254
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
