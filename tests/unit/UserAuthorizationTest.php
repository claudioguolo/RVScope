<?php

namespace Tests\Unit;

use App\Libraries\UserAuthorization;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAuthorizationTest extends TestCase
{
    #[DataProvider('roles')]
    public function testRolePermissions(string $role, bool $canEdit, bool $canAdminister): void
    {
        self::assertSame($canEdit, UserAuthorization::roleCanEditHosts($role));
        self::assertSame($canAdminister, UserAuthorization::roleCanAdminister($role));
    }

    public static function roles(): array
    {
        return [
            'user' => ['user', false, false],
            'editor' => ['editor', true, false],
            'admin' => ['admin', true, true],
            'unknown defaults to user' => ['unexpected', false, false],
        ];
    }
}
