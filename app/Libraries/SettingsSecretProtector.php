<?php

namespace App\Libraries;

use RuntimeException;

class SettingsSecretProtector
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($cipherText === false) {
            throw new RuntimeException('Não foi possível proteger a credencial SMTP.');
        }

        return base64_encode($iv . $tag . $cipherText);
    }

    public function decrypt(string $protectedValue): string
    {
        if ($protectedValue === '') {
            return '';
        }

        $payload = base64_decode($protectedValue, true);
        if ($payload === false || strlen($payload) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('A credencial SMTP armazenada é inválida.');
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr($payload, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($payload, self::IV_LENGTH + self::TAG_LENGTH);
        $plainText = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plainText === false) {
            throw new RuntimeException('Não foi possível recuperar a credencial SMTP.');
        }

        return $plainText;
    }

    public function configured(): bool
    {
        return $this->configuredKey() !== '';
    }

    private function key(): string
    {
        $configuredKey = $this->configuredKey();
        if (strlen($configuredKey) < 32) {
            throw new RuntimeException(
                'Defina security.settingsEncryptionKey no .env com pelo menos 32 caracteres.',
            );
        }

        return hash('sha256', $configuredKey, true);
    }

    private function configuredKey(): string
    {
        $processValue = getenv('security.settingsEncryptionKey');
        if ($processValue !== false && trim($processValue) !== '') {
            return trim($processValue);
        }

        return function_exists('env')
            ? trim((string) env('security.settingsEncryptionKey', ''))
            : '';
    }
}
