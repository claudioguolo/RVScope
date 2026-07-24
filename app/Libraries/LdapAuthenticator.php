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
        if ($password === '' || ! function_exists('ldap_connect')) {
            return false;
        }

        $username = strtolower(trim($username));
        if (! preg_match('/^[a-z0-9._-]+(?:@[a-z0-9.-]+)?$/i', $username)) {
            return false;
        }

        $configuration = (new AppSettingModel())->adConfiguration();
        if (! $configuration['enabled']
            || $configuration['host'] === ''
            || $configuration['domain'] === '') {
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
                return false;
            }

            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);

            $authenticated = @ldap_bind($connection, $bindUser, $password);
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
