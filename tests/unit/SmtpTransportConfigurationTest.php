<?php

namespace Tests\Unit;

use App\Libraries\SmtpTransportConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpTransportConfigurationTest extends TestCase
{
    public function testAllowsEncryptedSubmissionOn587(): void
    {
        self::assertSame(['port' => 587, 'crypto' => 'tls'], SmtpTransportConfiguration::fromArray([
            'port' => '587', 'crypto' => 'tls',
        ]));
    }

    public function testPreservesExistingSettingsWhenFieldsAreAbsent(): void
    {
        self::assertSame(['port' => 587, 'crypto' => ''], SmtpTransportConfiguration::fromArray([], 587, ''));
    }

    public function testRetainsOtherTransportOptions(): void
    {
        self::assertSame(['port' => 465, 'crypto' => 'ssl'], SmtpTransportConfiguration::fromArray([
            'port' => '465', 'crypto' => 'ssl',
        ]));
        self::assertSame(['port' => 25, 'crypto' => ''], SmtpTransportConfiguration::fromArray([
            'port' => '25', 'crypto' => 'none',
        ]));
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        SmtpTransportConfiguration::fromArray($input);
    }

    public static function invalidConfigurations(): array
    {
        return [
            [['port' => '0']],
            [['port' => '65536']],
            [['port' => '587.1']],
            [['port' => ['587']]],
            [['crypto' => 'invalid']],
            [['crypto' => ['tls']]],
        ];
    }
}
