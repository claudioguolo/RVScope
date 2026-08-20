<?php

namespace App\Libraries;

use App\Models\AdminUserModel;

final class UserAuthorization
{
    public const ROLE_USER = 'user';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_ADMIN = 'admin';

    public static function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return in_array($role, self::roles(), true) ? $role : self::ROLE_USER;
    }

    public static function roles(): array
    {
        return [self::ROLE_USER, self::ROLE_EDITOR, self::ROLE_ADMIN];
    }

    public static function currentRole(): string
    {
        $user = self::currentUser();
        if (! is_array($user)) {
            return self::ROLE_USER;
        }

        $role = self::normalizeRole((string) ($user['role'] ?? 'user'));
        session()->set('auth_role', $role);

        return $role;
    }

    public static function isAuthenticated(): bool
    {
        return (bool) session('user_authenticated') && is_array(self::currentUser());
    }

    public static function canEditHosts(): bool
    {
        return self::isAuthenticated()
            && self::roleCanEditHosts(self::currentRole());
    }

    public static function canAdminister(): bool
    {
        return self::isAuthenticated()
            && self::roleCanAdminister(self::currentRole());
    }

    public static function roleCanEditHosts(?string $role): bool
    {
        return in_array(self::normalizeRole($role), [self::ROLE_EDITOR, self::ROLE_ADMIN], true);
    }

    public static function roleCanAdminister(?string $role): bool
    {
        return self::normalizeRole($role) === self::ROLE_ADMIN;
    }

    private static function currentUser(): ?array
    {
        if (! (bool) session('user_authenticated')) {
            return null;
        }

        $userId = (int) (session('auth_user_id') ?: session('admin_user_id'));
        if ($userId <= 0) {
            return null;
        }

        $user = (new AdminUserModel())->find($userId);
        if (! is_array($user) || (int) ($user['is_active'] ?? 0) !== 1) {
            return null;
        }

        return $user;
    }
}
