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
        return $this->boolValue(self::AUTHENTICATED_REPORTS_KEY);
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

    private function boolValue(string $key): bool
    {
        return filter_var($this->value($key, '0'), FILTER_VALIDATE_BOOL);
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
