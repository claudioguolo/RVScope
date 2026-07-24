<?php

namespace App\Models;

use CodeIgniter\Model;
use Throwable;

class AppSettingModel extends Model
{
    public const AUTHENTICATED_REPORTS_KEY = 'authenticated_reports_enabled';

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
        try {
            $setting = $this->find(self::AUTHENTICATED_REPORTS_KEY);
        } catch (Throwable $exception) {
            log_message('warning', 'Configuração de acesso indisponível: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return false;
        }

        if (! is_array($setting)) {
            return false;
        }

        return filter_var(
            (string) ($setting['setting_value'] ?? '0'),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function setAuthenticatedReportsEnabled(bool $enabled): void
    {
        $data = [
            'setting_value' => $enabled ? '1' : '0',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (is_array($this->find(self::AUTHENTICATED_REPORTS_KEY))) {
            $this->update(self::AUTHENTICATED_REPORTS_KEY, $data);
            return;
        }

        $this->insert([
            'setting_key' => self::AUTHENTICATED_REPORTS_KEY,
            ...$data,
        ]);
    }
}
