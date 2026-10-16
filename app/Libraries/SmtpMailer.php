<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use RuntimeException;

class SmtpMailer
{
    public function sendTest(array $recipients, array $configuration, ?array $alternative): void
    {
        if ($configuration['username'] === '' || $configuration['password_encrypted'] === '') {
            throw new RuntimeException('Cadastre a conta exclusiva de testes na administração.');
        }
        $account = $alternative ?? [
            'username' => $configuration['username'],
            'password' => (new SettingsSecretProtector())->decrypt($configuration['password_encrypted']),
        ];
        $this->send($recipients, $configuration['subject'], $configuration['body'], $account, true, [
            'port' => $configuration['port'],
            'crypto' => $configuration['crypto'],
        ]);
    }

    public function send(string|array $recipient, string $subject, string $message, ?array $temporaryAccount = null, bool $plainText = false, ?array $transport = null): void
    {
        $settings = (new AppSettingModel())->smtpConfiguration();
        if (! $settings['enabled']) {
            throw new RuntimeException('O envio SMTP está desabilitado.');
        }

        $password = $temporaryAccount !== null
            ? $temporaryAccount['password']
            : (new SettingsSecretProtector())->decrypt((string) $settings['password_encrypted']);
        if ($temporaryAccount !== null) {
            $settings['username'] = $temporaryAccount['username'];
            $settings['from_email'] = $temporaryAccount['username'];
        }

        $email = new PrivateSmtpEmail();
        $email->initialize([
            'protocol' => 'smtp',
            'SMTPHost' => (string) $settings['host'],
            'SMTPUser' => (string) $settings['username'],
            'SMTPPass' => $password,
            'SMTPPort' => (int) ($transport['port'] ?? $settings['port']),
            'SMTPCrypto' => (string) ($transport['crypto'] ?? $settings['crypto']),
            'SMTPTimeout' => 10,
            'mailType' => $plainText ? 'text' : 'html',
            'charset' => 'UTF-8',
            'wordWrap' => true,
        ]);
        $email->setFrom((string) $settings['from_email'], (string) $settings['from_name']);
        $email->setTo($recipient);
        $email->setSubject($subject);
        $email->setMessage($message);

        if (! $email->send()) {
            throw new RuntimeException('O servidor SMTP recusou o envio da mensagem.');
        }
    }
}
