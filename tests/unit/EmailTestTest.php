<?php

namespace Tests\Unit;

use App\Libraries\EmailTestRecipients;
use App\Libraries\EmailTestUsage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmailTestTest extends TestCase
{
    public function testRecipientsAcceptSeparatorsAndDeduplicate(): void
    {
        self::assertSame(['a@example.org', 'b@example.org'], EmailTestRecipients::parse("A@example.org; b@example.org\na@example.org"));
    }

    public function testHeaderInjectionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRecipients::parse("a@example.org\r\nBcc: attacker@example.org");
    }

    public function testCombinedRecipientLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailTestRecipients::parse(implode(',', array_map(static fn ($i) => "user{$i}@example.org", range(1, 21))));
    }

    public function testUsageIsLimitedAcrossInstancesAndStoresOnlyIdentityAndTime(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rvscope-email-');
        try {
            (new EmailTestUsage($path))->record(12);
            (new EmailTestUsage($path))->record(13);
            $entries = array_map(static fn ($line) => json_decode($line, true), file($path, FILE_IGNORE_NEW_LINES));
            self::assertCount(2, $entries);
            self::assertSame(['user_id', 'timestamp', 'date'], array_keys($entries[0]));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Aguarde 60 segundos');
            (new EmailTestUsage($path))->record(12);
        } finally {
            unlink($path);
        }
    }

    public function testZeroIntervalAllowsConsecutiveTestsAndStillLogsUsage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rvscope-email-');
        try {
            $usage = new EmailTestUsage($path);
            $usage->record(12, 0);
            (new EmailTestUsage($path))->record(12, 0);
            self::assertCount(2, file($path, FILE_IGNORE_NEW_LINES));
        } finally {
            unlink($path);
        }
    }

    public function testConfiguredIntervalAppliesToPreviousUsage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rvscope-email-');
        try {
            file_put_contents($path, json_encode(['user_id' => 12, 'timestamp' => time() - 30, 'date' => gmdate('c')]) . "\n");
            (new EmailTestUsage($path))->record(12, 5);
            self::assertCount(2, file($path, FILE_IGNORE_NEW_LINES));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Aguarde 120 segundos');
            (new EmailTestUsage($path))->record(12, 120);
        } finally {
            unlink($path);
        }
    }

    public function testRejectsInvalidIntervals(): void
    {
        foreach ([-1, 3601, '1.5', ['0'], '', false] as $input) {
            try {
                EmailTestUsage::validateInterval($input);
                self::fail('Intervalo inválido foi aceito.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Informe um intervalo inteiro entre 0 e 3600 segundos.', $exception->getMessage());
            }
        }
        self::assertSame(0, EmailTestUsage::validateInterval('0'));
        self::assertSame(3600, EmailTestUsage::validateInterval('3600'));
    }
}
