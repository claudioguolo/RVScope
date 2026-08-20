<?php

namespace Tests\Unit;

use App\Libraries\RvtoolsOsFallbackResolver;
use PHPUnit\Framework\TestCase;

final class RvtoolsOsFallbackResolverTest extends TestCase
{
    public function testUsesLastKnownOsForPoweredOnVmWithEmptyToolsOs(): void
    {
        $resolver = new RvtoolsOsFallbackResolver();
        $inventory = $resolver->apply([
            'SRV-P-WSO2-WTS' => [
                'power_state' => 'poweredOn',
                'os_name' => '',
                'os_name_raw' => '',
                'included_in_reports' => false,
            ],
        ], ['SRV-P-WSO2-WTS' => 'Red Hat Enterprise Linux 7']);

        self::assertSame('Red Hat Enterprise Linux 7', $inventory['SRV-P-WSO2-WTS']['os_name']);
        self::assertTrue($inventory['SRV-P-WSO2-WTS']['included_in_reports']);
        self::assertSame('', $inventory['SRV-P-WSO2-WTS']['os_name_raw']);
        self::assertSame(['Red Hat Enterprise Linux 7' => 1], $resolver->summarize($inventory));
    }

    public function testDoesNotIncludePoweredOffVmOrVmWithoutHistory(): void
    {
        $resolver = new RvtoolsOsFallbackResolver();
        $inventory = $resolver->apply([
            'powered-off' => [
                'power_state' => 'poweredOff',
                'os_name' => '',
                'included_in_reports' => false,
            ],
            'unknown' => [
                'power_state' => 'poweredOn',
                'os_name' => '',
                'included_in_reports' => false,
            ],
        ], ['powered-off' => 'Red Hat Enterprise Linux 7']);

        self::assertFalse($inventory['powered-off']['included_in_reports']);
        self::assertFalse($inventory['unknown']['included_in_reports']);
        self::assertSame([], $resolver->summarize($inventory));
    }
}
