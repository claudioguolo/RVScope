<?php

namespace Tests\Unit;

use App\Libraries\OperatingSystemNameNormalizer;
use PHPUnit\Framework\TestCase;

final class OperatingSystemNameNormalizerTest extends TestCase
{
    public function testMatchesRvtoolsNormalizationRules(): void
    {
        $normalizer = new OperatingSystemNameNormalizer();

        self::assertSame('CentOS', $normalizer->normalize('CentOS 7 (64-bit)'));
        self::assertSame('Other', $normalizer->normalize('Other 3.x Linux (64-bit)'));
        self::assertSame('VMware Photon OS', $normalizer->normalize('VMware Photon OS (64-bit)'));
        self::assertSame('Red Hat Enterprise Linux 9', $normalizer->normalize('Red Hat Enterprise Linux 9 (64-bit)'));
    }
}
