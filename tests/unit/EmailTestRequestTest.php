<?php

namespace Tests\Unit;

use App\Libraries\EmailTestRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTestRequestTest extends TestCase
{
    private string|false $previousDomain;

    protected function setUp(): void
    {
        $this->previousDomain = getenv('emailTest.domain');
        putenv('emailTest.domain');
    }

    protected function tearDown(): void
    {
        putenv($this->previousDomain === false ? 'emailTest.domain' : 'emailTest.domain=' . $this->previousDomain);
    }

    public function testQuickIgnoresAlternativeFields(): void
    {
        self::assertSame(['recipients' => ['default@example.org'], 'account' => null], EmailTestRequest::resolve([
            'mode' => 'quick', 'use_custom_account' => '1', 'use_custom_recipients' => '1', 'username' => 'attacker@other.org', 'password' => 'temporary',
            'custom_recipients' => 'other@example.org',
        ], 'default@example.org'));
    }

    public function testCustomUsesDefaultDomainAndDefaultRecipientsWhenEmpty(): void
    {
        $resolved = EmailTestRequest::resolve(['mode' => 'custom', 'use_custom_account' => '1', 'username' => 'conta', 'password' => 'temporary'], 'default@example.org');
        self::assertSame('conta@envio.tjmt.jus.br', $resolved['account']['username']);
        self::assertSame(['default@example.org'], $resolved['recipients']);
    }

    public function testCustomRecipientsReplaceDefaults(): void
    {
        $resolved = EmailTestRequest::resolve(['mode' => 'custom', 'use_custom_account' => '1', 'username' => 'conta', 'password' => 'temporary', 'use_custom_recipients' => '1', 'custom_recipients' => 'custom@example.org'], 'default@example.org');
        self::assertSame(['custom@example.org'], $resolved['recipients']);
    }

    public function testCustomRecipientsOnlyKeepDefaultAccountAndIgnoreCredentialFields(): void
    {
        $resolved = EmailTestRequest::resolve([
            'mode' => 'custom', 'use_custom_recipients' => '1',
            'username' => ['invalid'], 'password' => ['ignored'], 'custom_recipients' => 'custom@example.org',
        ], 'default@example.org');
        self::assertSame(['recipients' => ['custom@example.org'], 'account' => null], $resolved);
    }

    public function testCustomAccountOnlyKeepsDefaultRecipientsAndIgnoresRecipientField(): void
    {
        $resolved = EmailTestRequest::resolve([
            'mode' => 'custom', 'use_custom_account' => '1', 'username' => 'conta', 'password' => 'temporary',
            'custom_recipients' => ['ignored'],
        ], 'default@example.org');
        self::assertSame(['default@example.org'], $resolved['recipients']);
        self::assertSame('conta@envio.tjmt.jus.br', $resolved['account']['username']);
    }

    public function testCustomWithNoChecksKeepsBothDefaults(): void
    {
        self::assertSame(['recipients' => ['default@example.org'], 'account' => null], EmailTestRequest::resolve([
            'mode' => 'custom', 'username' => 'ignored', 'password' => 'ignored', 'custom_recipients' => 'ignored',
        ], 'default@example.org'));
    }

    public function testCheckedCustomRecipientsRequireAnAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'custom', 'use_custom_recipients' => '1', 'custom_recipients' => ''], 'default@example.org');
    }

    public function testCustomRecipientsCanBeUsedWithoutDefaultRecipients(): void
    {
        self::assertSame(['custom@example.org'], EmailTestRequest::resolve([
            'mode' => 'custom', 'use_custom_recipients' => '1', 'custom_recipients' => 'custom@example.org',
        ], '')['recipients']);
    }

    public function testMalformedCheckboxValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'custom', 'use_custom_account' => ['1']], 'default@example.org');
    }

    public function testOtherDomainCannotBeSubmittedDirectly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'custom', 'use_custom_account' => '1', 'username' => 'conta@other.org', 'password' => 'temporary'], 'default@example.org');
    }

    public function testCustomRequiresPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'custom', 'use_custom_account' => '1', 'username' => 'conta'], 'default@example.org');
    }

    public function testUnknownModeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::resolve(['mode' => 'other'], 'default@example.org');
    }

    public function testCustomUsesEnvironmentDomainAndIgnoresSubmittedDomain(): void
    {
        putenv('emailTest.domain=mail.example.org');
        $resolved = EmailTestRequest::resolve([
            'mode' => 'custom', 'use_custom_account' => '1', 'username' => 'conta', 'password' => 'temporary', 'domain' => 'attacker.org',
        ], 'default@example.org');
        self::assertSame('conta@mail.example.org', $resolved['account']['username']);
        self::assertTrue(EmailTestRequest::matchesDomain('conta@mail.example.org'));
        self::assertFalse(EmailTestRequest::matchesDomain('conta@envio.tjmt.jus.br'));
        self::assertFalse(EmailTestRequest::matchesDomain('conta@sub.mail.example.org'));
    }

    public function testNormalizesDomainWithOptionalAtSign(): void
    {
        putenv('emailTest.domain= @MAIL.EXAMPLE.ORG ');
        self::assertSame('mail.example.org', EmailTestRequest::domain());
        self::assertSame('conta@mail.example.org', EmailTestRequest::account('conta'));
    }

    #[DataProvider('invalidDomains')]
    public function testRejectsInvalidEnvironmentDomain(string $domain): void
    {
        putenv('emailTest.domain=' . $domain);
        $this->expectException(InvalidArgumentException::class);
        EmailTestRequest::domain();
    }

    public static function invalidDomains(): array
    {
        return [
            [''],
            ['https://example.org'],
            ['example.org:587'],
            ['example.org/'],
            ['@@example.org'],
            ['example.org@other.org'],
            ["example.org\r\nBcc: attacker@example.org"],
        ];
    }
}
