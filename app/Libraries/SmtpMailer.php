<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use RuntimeException;

class SmtpMailer
{
    public function send(string $recipient, string $subject, string $message): void
    {
        $settings = (new AppSettingModel())->smtpConfiguration();
        if (! $settings['enabled']) {
            throw new RuntimeException('O envio SMTP está desabilitado.');
        }

        $password = (new SettingsSecretProtector())->decrypt(
            (string) $settings['password_encrypted'],
        );

        $email = service('email');
        $email->initialize([
            'protocol' => 'smtp',
            'SMTPHost' => (string) $settings['host'],
            'SMTPUser' => (string) $settings['username'],
            'SMTPPass' => $password,
            'SMTPPort' => (int) $settings['port'],
            'SMTPCrypto' => (string) $settings['crypto'],
            'SMTPTimeout' => 10,
            'mailType' => 'html',
            'charset' => 'UTF-8',
            'wordWrap' => true,
        ]);
        $email->setFrom((string) $settings['from_email'], (string) $settings['from_name']);
        $email->setTo($recipient);
        $email->setSubject($subject);
        $email->setMessage($message);

        if (! $email->send()) {
            log_message('error', 'Falha no envio SMTP: {debug}', [
                'debug' => strip_tags($email->printDebugger(['headers'])),
            ]);
            throw new RuntimeException('O servidor SMTP recusou o envio da mensagem.');
        }
    }
}
