<?php

namespace Tests\Unit;

use App\Libraries\EmailTestRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTestRequestTest extends TestCase
{
    public function testQuickIgnoresAlternativeFields(): void
    {
        self::assertSame(['recipients' => ['default@example.org'], 'account' => null], EmailTestRequest::resolve([
            'mode' => 'quick', 'username' => 'attacker@other.org', 'password' => 'temporary',
            'custom_recipients' => 'other@example.org',
        ], 'default@example.org'));
    }

    public function testCustomUsesFixedDomainAndDefaultRecipientsWhenEmpty(): void
    {
        $resolved = EmailTestRequest::resolve(['mode' => 'custom', 'username' => 'conta', 'password' => 'temporary'], 'default@example.org');
        self::assertSame('conta@envio.tjmt.jus.br', $resolved['account']['username']);
        self::assertSame(['default@example.org'], $resolved['recipients']);
    }

    public function testCustomRecipientsReplaceDefaults(): void
    {
        $resolved = EmailTestRequest::resolve(['mode' => 'custom', 'username' => 'conta', 'password' => 'temporary', 'custom_recipients' => 'custom@example.org'], 'default@example.org');
        self::assertSame(['custom@example.org'], $resolved['recipients']);
    }

    public function testOtherDomainCannotBeSubmittedDirectly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'custom', 'username' => 'conta@other.org', 'password' => 'temporary'], 'default@example.org');
    }

    public function testCustomRequiresPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'custom', 'username' => 'conta'], 'default@example.org');
    }

    public function testUnknownModeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'other'], 'default@example.org');
    }
}
