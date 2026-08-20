<?php

namespace Tests\Unit;

use App\Libraries\LocalUserAuthenticator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalUserAuthenticatorTest extends TestCase
{
    public function testAcceptsActiveLocalUserWithCorrectPassword(): void
    {
        $user = [
            'auth_source' => 'local',
            'is_active' => 1,
            'password_hash' => password_hash('correct-password', PASSWORD_DEFAULT),
        ];

        self::assertTrue((new LocalUserAuthenticator())->verify($user, 'correct-password'));
    }

    #[DataProvider('rejectedUsers')]
    public function testRejectsInvalidLocalAuthentication(?array $user, string $password): void
    {
        self::assertFalse((new LocalUserAuthenticator())->verify($user, $password));
    }

    public static function rejectedUsers(): array
    {
        $hash = password_hash('correct-password', PASSWORD_DEFAULT);

        return [
            'missing user' => [null, 'correct-password'],
            'inactive user' => [['auth_source' => 'local', 'is_active' => 0, 'password_hash' => $hash], 'correct-password'],
            'AD user' => [['auth_source' => 'ad', 'is_active' => 1, 'password_hash' => $hash], 'correct-password'],
            'wrong password' => [['auth_source' => 'local', 'is_active' => 1, 'password_hash' => $hash], 'wrong-password'],
            'empty hash' => [['auth_source' => 'local', 'is_active' => 1, 'password_hash' => ''], 'correct-password'],
        ];
    }
}
