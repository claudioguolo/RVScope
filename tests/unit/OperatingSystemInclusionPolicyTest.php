<?php

namespace Tests\Unit;

use App\Libraries\OperatingSystemInclusionPolicy;
use PHPUnit\Framework\TestCase;

final class OperatingSystemInclusionPolicyTest extends TestCase
{
    public function testAdministrativePolicyOverridesLegacyDefaults(): void
    {
        $policy = new OperatingSystemInclusionPolicy();

        self::assertTrue($policy->included('poweredOn', 'Microsoft Windows Server 2022', 'Microsoft Windows Server 2022', [
            'Microsoft Windows Server 2022' => false,
        ]));
        self::assertFalse($policy->included('poweredOn', 'Ubuntu Linux', 'Ubuntu Linux', [
            'Ubuntu Linux' => true,
        ]));
    }

    public function testPreservesDefaultsForNewOperatingSystemsUntilConfigured(): void
    {
        $policy = new OperatingSystemInclusionPolicy();

        self::assertFalse($policy->included('poweredOn', 'VMware Photon OS', 'VMware Photon OS', []));
        self::assertTrue($policy->included('poweredOn', 'Red Hat Enterprise Linux 9', 'Red Hat Enterprise Linux 9', []));
        self::assertFalse($policy->included('poweredOff', 'Ubuntu Linux', 'Ubuntu Linux', []));
        self::assertFalse($policy->included('poweredOn', '', '', []));
    }
}
