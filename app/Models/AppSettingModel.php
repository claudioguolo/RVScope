<?php

namespace App\Models;

use CodeIgniter\Model;
use Throwable;

class AppSettingModel extends Model
{
    public const AUTHENTICATED_REPORTS_KEY = 'authenticated_reports_enabled';
    public const AD_ENABLED_KEY = 'ad_enabled';
    public const AD_HOST_KEY = 'ad_host';
    public const AD_PORT_KEY = 'ad_port';
    public const AD_DOMAIN_KEY = 'ad_domain';
    public const SMTP_ENABLED_KEY = 'smtp_enabled';
    public const SMTP_HOST_KEY = 'smtp_host';
    public const SMTP_PORT_KEY = 'smtp_port';
    public const SMTP_CRYPTO_KEY = 'smtp_crypto';
    public const SMTP_USERNAME_KEY = 'smtp_username';
    public const SMTP_PASSWORD_KEY = 'smtp_password_encrypted';
    public const SMTP_FROM_EMAIL_KEY = 'smtp_from_email';
    public const SMTP_FROM_NAME_KEY = 'smtp_from_name';

    protected $table = 'app_settings';
    protected $primaryKey = 'setting_key';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'updated_at',
    ];

    public function authenticatedReportsEnabled(): bool
    {
        return $this->boolValue(
            self::AUTHENTICATED_REPORTS_KEY,
            ENVIRONMENT === 'production',
        );
    }

    public function setAuthenticatedReportsEnabled(bool $enabled): void
    {
        $this->setValue(self::AUTHENTICATED_REPORTS_KEY, $enabled ? '1' : '0');
    }

    public function adConfiguration(): array
    {
        return [
            'enabled' => $this->boolValue(self::AD_ENABLED_KEY),
            'host' => $this->value(self::AD_HOST_KEY),
            'port' => (int) $this->value(self::AD_PORT_KEY, '636'),
            'domain' => $this->value(self::AD_DOMAIN_KEY),
        ];
    }

    public function setAdConfiguration(bool $enabled, string $host, int $port, string $domain): void
    {
        $this->db->transStart();
        $this->setValue(self::AD_ENABLED_KEY, $enabled ? '1' : '0');
        $this->setValue(self::AD_HOST_KEY, $host);
        $this->setValue(self::AD_PORT_KEY, (string) $port);
        $this->setValue(self::AD_DOMAIN_KEY, $domain);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Falha ao persistir a configuração do Active Directory.');
        }
    }

    public function smtpConfiguration(): array
    {
        return [
            'enabled' => $this->boolValue(self::SMTP_ENABLED_KEY),
            'host' => $this->value(self::SMTP_HOST_KEY),
            'port' => (int) $this->value(self::SMTP_PORT_KEY, '587'),
            'crypto' => $this->value(self::SMTP_CRYPTO_KEY, 'tls'),
            'username' => $this->value(self::SMTP_USERNAME_KEY),
            'password_encrypted' => $this->value(self::SMTP_PASSWORD_KEY),
            'from_email' => $this->value(self::SMTP_FROM_EMAIL_KEY),
            'from_name' => $this->value(self::SMTP_FROM_NAME_KEY, 'RVScope'),
        ];
    }

    public function setSmtpConfiguration(array $configuration): void
    {
        $this->db->transStart();
        $this->setValue(self::SMTP_ENABLED_KEY, $configuration['enabled'] ? '1' : '0');
        $this->setValue(self::SMTP_HOST_KEY, (string) $configuration['host']);
        $this->setValue(self::SMTP_PORT_KEY, (string) $configuration['port']);
        $this->setValue(self::SMTP_CRYPTO_KEY, (string) $configuration['crypto']);
        $this->setValue(self::SMTP_USERNAME_KEY, (string) $configuration['username']);
        if ((string) $configuration['password_encrypted'] !== '') {
            $this->setValue(self::SMTP_PASSWORD_KEY, (string) $configuration['password_encrypted']);
        }
        $this->setValue(self::SMTP_FROM_EMAIL_KEY, (string) $configuration['from_email']);
        $this->setValue(self::SMTP_FROM_NAME_KEY, (string) $configuration['from_name']);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Falha ao persistir a configuração SMTP.');
        }
    }

    public function value(string $key, string $default = ''): string
    {
        try {
            $setting = $this->find($key);
        } catch (Throwable $exception) {
            log_message('warning', 'Configuração da aplicação indisponível: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return $default;
        }

        return is_array($setting)
            ? (string) ($setting['setting_value'] ?? $default)
            : $default;
    }

    private function boolValue(string $key, bool $default = false): bool
    {
        return filter_var($this->value($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    private function setValue(string $key, string $value): void
    {
        $data = [
            'setting_value' => $value,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (is_array($this->find($key))) {
            $this->update($key, $data);
            return;
        }

        $this->insert([
            'setting_key' => $key,
            ...$data,
        ]);
    }
}
