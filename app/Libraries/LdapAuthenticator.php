<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use Throwable;

class LdapAuthenticator
{
    public function enabled(): bool
    {
        return (new AppSettingModel())->adConfiguration()['enabled'];
    }

    public function authenticate(string $username, string $password): bool
    {
        if ($password === '') {
            log_message('warning', 'Autenticação LDAPS recusada: senha não informada.');
            return false;
        }

        if (! function_exists('ldap_connect')) {
            log_message('error', 'Autenticação LDAPS indisponível: extensão LDAP não carregada.');
            return false;
        }

        $username = strtolower(trim($username));
        if (! preg_match('/^[a-z0-9._-]+(?:@[a-z0-9.-]+)?$/i', $username)) {
            log_message('warning', 'Autenticação LDAPS recusada: formato de usuário inválido.');
            return false;
        }

        $configuration = (new AppSettingModel())->adConfiguration();
        if (! $configuration['enabled']
            || $configuration['host'] === ''
            || $configuration['domain'] === '') {
            log_message(
                'warning',
                'Autenticação LDAPS indisponível: integração desabilitada ou configuração incompleta.',
            );
            return false;
        }

        $host = (string) $configuration['host'];
        $port = (int) $configuration['port'];
        $domain = strtolower((string) $configuration['domain']);
        $bindUser = str_contains($username, '@')
            ? $username
            : $username . '@' . $domain;

        if (str_contains($bindUser, '@')
            && ! str_ends_with(strtolower($bindUser), '@' . $domain)) {
            log_message('warning', 'Autenticação LDAPS recusada: domínio UPN diferente do configurado.');
            return false;
        }

        try {
            $connectionHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
            $caCertificate = '/etc/ssl/private/ad-ca.crt';
            if (is_file($caCertificate)) {
                ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caCertificate);
            }
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);
            $connection = ldap_connect(sprintf('ldaps://%s:%d', $connectionHost, $port));
            if ($connection === false) {
                log_message(
                    'error',
                    'Autenticação LDAPS indisponível: não foi possível inicializar a conexão com {host}:{port}.',
                    ['host' => $host, 'port' => $port],
                );
                return false;
            }

            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);

            $authenticated = @ldap_bind($connection, $bindUser, $password);
            if (! $authenticated) {
                $diagnosticMessage = '';
                @ldap_get_option($connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnosticMessage);
                $adCode = 'não informado';
                if (preg_match('/\bdata\s+([0-9a-f]+)\b/i', $diagnosticMessage, $matches) === 1) {
                    $adCode = strtolower($matches[1]);
                }

                log_message(
                    'warning',
                    'Falha no bind LDAPS em {host}:{port}: código LDAP {ldapCode}, erro "{ldapError}", código AD {adCode}.',
                    [
                        'host' => $host,
                        'port' => $port,
                        'ldapCode' => ldap_errno($connection),
                        'ldapError' => ldap_error($connection),
                        'adCode' => $adCode,
                    ],
                );
            } else {
                log_message(
                    'notice',
                    'Bind LDAPS concluído com sucesso em {host}:{port}.',
                    ['host' => $host, 'port' => $port],
                );
            }
            ldap_unbind($connection);

            return $authenticated;
        } catch (Throwable $exception) {
            log_message('warning', 'Falha na autenticação LDAPS: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
