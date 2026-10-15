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
}
