<?php

namespace Tests\Unit;

use App\Libraries\OperatingSystemDisplayName;
use PHPUnit\Framework\TestCase;

final class OperatingSystemDisplayNameTest extends TestCase
{
    public function testRemovesSingularAndPlural64BitSuffix(): void
    {
        $formatter = new OperatingSystemDisplayName();

        self::assertSame(
            'Red Hat Enterprise Linux 9',
            $formatter->clean('Red Hat Enterprise Linux 9 (64-bit)')
        );
        self::assertSame(
            'Red Hat Enterprise Linux 10',
            $formatter->clean('Red Hat Enterprise Linux 10 (64-bits)')
        );
    }

    public function testPreservesOtherOperatingSystemText(): void
    {
        self::assertSame(
            'Microsoft Windows Server 2022',
            (new OperatingSystemDisplayName())->clean('Microsoft Windows Server 2022')
        );
    }
}
