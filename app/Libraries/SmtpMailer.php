<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use RuntimeException;

class SmtpMailer
{
    public function send(string|array $recipient, string $subject, string $message, ?array $temporaryAccount = null, bool $plainText = false): void
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
            'SMTPPort' => (int) $settings['port'],
            'SMTPCrypto' => (string) $settings['crypto'],
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
