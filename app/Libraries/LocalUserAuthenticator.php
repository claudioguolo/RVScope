<?php

namespace App\Libraries;

final class LocalUserAuthenticator
{
    public function verify(?array $user, string $password): bool
    {
        if (! is_array($user)
            || (string) ($user['auth_source'] ?? 'local') !== 'local'
            || (int) ($user['is_active'] ?? 0) !== 1
            || $password === '') {
            return false;
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');

        return $passwordHash !== '' && password_verify($password, $passwordHash);
    }
}
